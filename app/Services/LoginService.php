<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Adminlogin\Models\AdminLoginHistory;
use App\Api\Admin\Modules\Resources\Models\Resources;
use App\Api\Teacher\Modules\Signup\Models\BillingEmail;
use App\Api\Teacher\Modules\Signup\Models\BillingInvoiceUsers;
use App\Jobs\InvoiceEmailJob;
use App\Models\ModelHasRoles;
use App\Models\User;
use App\Models\UserLoginHistory;
use App\Models\UserSubscription;
use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginService
{
    use ApiResponse, TransactionWrapper;

    protected DataSecurityService $dataSecurityService;
    protected SubscriptionService $subscriptionService;
    protected StripeService $stripeService;

    public function __construct(DataSecurityService $dataSecurityService, SubscriptionService $subscriptionService, StripeService $stripeService)
    {
        $this->dataSecurityService = $dataSecurityService;
        $this->subscriptionService = $subscriptionService;
        $this->stripeService = $stripeService;
    }
    /**
     * Handle user signup and assign a role.
     *
     * @param Request $request
     *
     * @return array
     */
    public function userSignup(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $validationErrors = $request->payment_type === UserSubscription::BACS
                ? $this->validateSignupforBacs($request)
                : $this->validateSignupForCard($request);
            if ($validationErrors) {
                return $this->validationErrorResponse($validationErrors);
            }
            $request->payment_type === UserSubscription::BACS ? $this->createUserForBacs($request) : $this->createUserForCard($request);

            return $this->successResponse(null, 'Registration complete. Welcome aboard!');
        });
    }
    /**
     * Handle the create user for signup (Pay by Card)
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUserForCard($request)
    {
        $isSubscribed = (bool) $request->is_subscribed;
        $isTrail = (bool) $request->is_trail;
        $roleId = $request->user_type;
        $user = $this->createUserFromRequest(
            $request->all(),
            $isSubscribed,
            $isTrail
        );
        $this->assignRoleToUser($user, (int) $roleId);
        $resourceId = $this->decryptedValues($request->resource_id);
        $type = $this->storeUserSubscription($resourceId, $user->id);
        if ($isTrail) {
            $this->subscriptionService->buildTrailHistory($user->id, $resourceId);
        } else {
            $this->subscriptionService->buildSubscriptionHistory($type['id'], $resourceId);
        }
        $this->stripeService->createCustomers($request, $user->id);

        return $user;
    }
    /**
     * Handle the create users for signup (Pay by Bacs)
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUserForBacs($request)
    {
        $billingInvoice = $this->createBillingInvoice($request->invoice_email);
        $role = Role::where('id', $request->user_type)->where('type', 1)->first();
        $emails = array_filter(array_map('trim', explode(',', $request->school_email)));
        $resourceIds = is_array($request->resource_id)
            ? array_filter(array_map('trim', $request->resource_id))
            : [];
        if (count($emails) !== count($resourceIds)) {
            return $this->failedResponse('Email and Resource ID count mismatch.');
        }
        $mappedData = array_map(null, $emails, $resourceIds);

        return $this->createAndAssignUsers($mappedData, $role, $billingInvoice->id);
    }

    /**
     * @param Request $request
     * @param  string $type
     *
     * @return array
     */
    public function login(Request $request, string $type): array
    {
        return $this->runInTransaction(function () use ($request, $type) {
            $errors = $this->validate($request);
            if ($errors) {
                return $this->validationErrorResponse($errors);
            }
            [$user, $roleData, $error] = $this->authenticateUser($request, $type);
            if ($error) {
                return $this->failedResponse($error);
            }
            $token = $this->generateToken($user, $roleData['roleId'] ?? '');
            $userData = $this->buildUserDataArray($user, $roleData, $token);
            $this->handleLoginHistory($user, $request, $roleData);
            // $encryptedUserData = $this->encryptUserData($userData);
            return $this->successResponse($userData, 'You have successfully logged in');
        });
    }
    /**
     * Handle the Forgot Password request.
     *
     * @param Request $request
     *
     * @return array
     */
    public function forgotPassword(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $validateError = $this->validateForgotPassword($request);
            if ($validateError) {
                return $this->validationErrorResponse($validateError);
            }
            $user = User::where('email', $request->username)->first();
            $newPassword = Str::random(10);
            $user->password = Hash::make($newPassword);
            $user->save();
            $this->sendUserEmail($user->email, $newPassword, 'forgot');

            return $this->successResponse(null, 'Password reset email sent successfully!');
        });
    }
    /**
     * Handle user logout by invalidating the JWT token.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function logout()
    {
        return $this->runInTransaction(function () {
            JWTAuth::invalidate(JWTAuth::getToken());

            return $this->successResponse(null, 'Successfully logged out');
        });
    }
    /**
     * Creates a user from signup request and subscription details.
     *
     * @param Request $request The HTTP request with user info
     * @param bool $isSubscribed Whether the user is subscribed
     * @param bool $isTrail Whether the subscription is a trial
     * @param string|null $subscriptionStartDate Trial/subscription start date
     * @param string|null $subscriptionEndDate Trial/subscription end date
     */
    public function createUserFromRequest(array $data, bool $isSubscribed, bool $isTrail)
    {
        return User::create([
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'payment_type' => $data['payment_type'] ?? null,
            // 'language_code' => $data['language_code'] ?? 1,
            'language_code' => UserSubscription::STATUS_ONE,
            'is_subscribed' => $isSubscribed,
            'is_trail' => $isTrail,
            'name' => $data['name'] ?? '',
            'is_customer' => $data['isCustomer'] ?? 0,
            'is_activated' => $data['isActivated'] ?? 0,
        ]);
    }
    /**
     * Handle login history by updating the previous record if incomplete,
     * and creating a new login record.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Http\Request  $request
     * @param array<string, string> $roleData
     *
     * @return void
     */
    protected function handleLoginHistory(User $user, Request $request, array $roleData): void
    {
        $this->updatePreviousLoginRecord($user, $roleData);
        $this->createLoginRecord($user, $request, $roleData);
    }
    /**
     * Update the previous login record's logout time and session duration
     * if it has no logout time set.
     *
     * @param  \App\Models\User  $user
     * @param array<string, string> $roleData
     *
     * @return void
     */
    protected function updatePreviousLoginRecord(User $user, array $roleData): void
    {
        $historyModel = $this->getLoginHistoryModelClass($roleData);
        $this->closePreviousLoginRecord($historyModel, $user->id);
    }
    /**
     * Get the appropriate login history model class based on role.
     *
     * @param  array<string, mixed>  $roleData
     *
     * @return class-string
     */
    protected function getLoginHistoryModelClass(array $roleData): string
    {
        return $roleData['roleId'] === ModelHasRoles::ADMIN
            ? AdminLoginHistory::class
            : UserLoginHistory::class;
    }
    /**
     * Close the previous login session by setting logout time and duration.
     *
     * @param  string  $historyModel
     * @param  int     $userId
     *
     * @return void
     */
    protected function closePreviousLoginRecord(string $historyModel, int $userId): void
    {
        $lastRecord = $historyModel::where('user_id', $userId)->latest()->first();

        if ($lastRecord && ! $lastRecord->logouttime) {
            $tokenExpiry = config('jwt.ttl');
            $loginTime = Carbon::parse($lastRecord->logintime);
            $logoutTime = $loginTime->copy()->addMinutes($tokenExpiry);

            $lastRecord->logouttime = $logoutTime;
            $lastRecord->duration = DB::raw("TIMESTAMPDIFF(SECOND, logintime, '{$logoutTime}')");
            $lastRecord->save();
        }
    }
    /**
     * Create a new login history record for the current user.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Http\Request  $request
     * @param array<string, string> $roleData
     *
     * @return void
     */
    protected function createLoginRecord(User $user, Request $request, array $roleData): void
    {
        $historyModel = $this->getLoginHistoryModelClass($roleData);

        $historyModel::create([
            'user_id' => $user->id,
            'logintime' => now(),
            'ipaddress' => $request->ip(),
            'useragent' => $request->userAgent(),
        ]);
    }
    /**
     * Create or store the billing email record for the invoice.
     *
     * @param string|null $invoiceEmail
     *
     * @return BillingEmail
     */
    private function createBillingInvoice(?string $invoiceEmail): BillingEmail
    {
        return BillingEmail::create([
            'invoice_email' => $invoiceEmail ?? '',
        ]);
    }
    /**
     * Create users from email list and assign them to a role and invoice.
     *
     * @param array $emails
     * @param Role|null $role
     * @param int $billingInvoiceId
     * @param array $resources
     *
     * @return User|null  The last created user
     */
    private function createAndAssignUsers(array $mappedData, ?Role $role, int $billingInvoiceId): ?User
    {
        $lastUser = null;
        foreach ($mappedData as [$email, $encryptedResourceId]) {
            $password = Str::random(10);
            $user = $this->createUser($email, $password);
            $this->assignRoleIfPresent($user, $role);
            $this->linkUserToInvoice($user, $billingInvoiceId);
            $resourceId = $this->decryptedValues($encryptedResourceId);
            $type = $this->storeUserSubscription($resourceId, $user->id);
            $this->subscriptionService->buildSubscriptionHistory($type['id'], $resourceId);
            // $this->sendUserEmail($email, $password, 'newuser');
            $lastUser = $user;
        }
        if ($billingInvoiceId) {
            InvoiceEmailJob::dispatch($billingInvoiceId);
        }
        return $lastUser;
    }
    /**
     * Create a user with given credentials.
     */
    private function createUser(string $email, string $password): User
    {
        $name = Str::title(str_replace(['.', '_'], ' ', Str::before($email, '@')));

        return User::create([
            'email' => $email,
            'name' => $name,
            'password' => Hash::make($password),
            'payment_type' => UserSubscription::BACS,
        ]);
    }
    /**
     * Assign role to user if role is not null.
     */
    private function assignRoleIfPresent(User $user, ?Role $role): void
    {
        if ($role) {
            $user->assignRole($role->name);
        }
    }
    /**
     * Link the created user to the billing invoice.
     */
    private function linkUserToInvoice(User $user, int $billingInvoiceId): void
    {
        BillingInvoiceUsers::create([
            'billing_invoice_id' => $billingInvoiceId,
            'user_id' => $user->id,
        ]);
    }
    /**
     * Retrieve a user by email with limited fields.
     *
     * @param  string  $email
     *
     * @return \App\Models\User|null  Returns the user instance or null if not found.
     */
    private function getUserByEmail(string $email): ?User
    {
        return User::select('id', 'email', 'password', 'name')
            ->where('email', $email)
            ->first();
    }
    /**
     * Check if the given password matches the hashed password.
     *
     * @param  string  $inputPassword
     * @param  string  $hashedPassword
     *
     * @return bool  True if passwords match, false otherwise.
     */
    private function checkPassword(string $inputPassword, string $hashedPassword): bool
    {
        return Hash::check($inputPassword, $hashedPassword);
    }
    /**
     * Retrieve the role ID and name for the given user ID.
     *
     * @param  int  $userId
     *
     * @return array  Returns an array with 'roleId' and 'roleName', or an empty array if no role is found.
     */
    private function getUserRole(int $userId): array
    {
        $roles = ModelHasRoles::where('model_id', $userId)->first();

        if (! $roles) {
            return [];
        }

        $roleName = Role::select('display_name')->where('id', $roles->role_id)->first();

        return [
            'roleId' => $roles->role_id,
            'roleName' => $roleName->display_name ?? '',
        ];
    }
    /**
     * Generate JWT token for the authenticated user.
     *
     * @param User $user
     * @param string|int $roleId
     *
     * @return string
     */
    private function generateToken(User $user, string|int $roleId): string
    {
        $claimsBlob = Crypt::encryptString(json_encode([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'roleId' => $roleId,
        ]));

        $customClaims = [
            'encryptedData' => $claimsBlob,
            'iss' => 'Admin',
            'iat' => (int) now()->timestamp,
        ];

        return JWTAuth::claims($customClaims)->fromUser($user);
    }
    /**
     * Build structured user data array.
     *
     * @param User $user
     * @param array<string, string> $roleData
     * @param string $token
     *
     * @return array
     */
    private function buildUserDataArray(User $user, array $roleData, string $token): array
    {
        $expIn = config('jwt.ttl') * 60;
        return [
            'userId' => $user->id,
            'email' => $user->email,
            'role' => $roleData['roleName'] ?? '',
            'roleId' => $roleData['roleId'] ?? '',
            'token' => $token,
            'exp_in' => $expIn,
        ];
    }
    /**
     * Encrypt user data into a token-safe string.
     *
     * @param array<string, string> $userData
     *
     * @return string
     */
    private function encryptUserData(array $userData): string
    {
        return $this->dataSecurityService->encrypt($userData);
    }
    private function validate(Request $request)
    {
        $rules = [
            'username' => 'required|email',
            'password' => 'required',
        ];
        $messages = [
            'username.required' => 'Email is required',
            'username.email' => 'Must be a valid email address.',
            'password.required' => 'Password is required',
        ];

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    private function validateForgotPassword(Request $request)
    {
        $rules = [
            'username' => 'required|email|exists:users,email',
        ];
        $messages = [
            'username.required' => 'Email is required.',
            'username.email' => 'Must be a valid email address.',
            'username.exists' => 'User not found with this email.',
        ];

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validateSignupForCard(Request $request): ?array
    {
        $rules = $this->getSignupValidationRules();
        $messages = $this->getSignupValidationMessages();

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * Get validation rules for signup.
     *
     * @return array
     */
    private function getSignupValidationRules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[@$!%*#?&]/',
            ],
            'confirm_password' => 'required|same:password',
        ];
    }
    /**
     * Get custom validation messages for signup.
     *
     * @return array
     */
    private function getSignupValidationMessages(): array
    {
        return [
            'email.required' => 'Email is required',
            'email.email' => 'Enter a valid email',
            'email.unique' => 'Email already exists',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be 8+ chars',
            'password.regex' => 'Use 1 uppercase & 1 special char',
            'confirm_password.required' => 'Confirm Password is required',
            'confirm_password.same' => 'Confirm Password must match',
        ];
    }
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validateSignupforBacs(Request $request): ?array
    {
        $rules = [
            'invoice_email' => 'required|email|unique:billing_emails,invoice_email',
            'school_email' => [
                'required',
                function ($_attribute, $value, $fail) {
                    $this->validateSchoolEmails($value, $fail);
                },
            ],
        ];
        $messages = [
            'invoice_email.required' => 'Billing Email required',
            'invoice_email.email' => 'Provide valid billing email address',
            'invoice_email.unique' => 'Billing email registered already',
            'school_email.required' => 'School Primary Email required',
        ];

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    private function validateSchoolEmails(string $value, callable $fail): void
    {
        $emails = array_filter(array_map('trim', explode(',', $value)));
        if (count($emails) === 0) {
            $fail('At least one email must be provided.');
            return;
        }

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fail("Email address '{$email}' invalid.");
                return;
            }

            if (User::where('email', $email)->exists()) {
                $fail("'{$email}' registered already");
                return;
            }
        }
    }
    /**
     * Authenticate the user by verifying credentials and role permissions.
     *
     * @param Request $request The login request containing username and password.
     * @param string $type The type of user attempting to log in ('user' or 'admin').
     *
     * @return array Returns an array with [user, roleData, errorMessage].
     *               If authentication fails, user and roleData are null, and errorMessage is set.
     */
    private function authenticateUser(Request $request, string $type): array
    {
        $user = $this->getUserByEmail($request->username);
        if (! $user || ! $this->checkPassword($request->password, $user->password)) {
            return [null, null, 'Username / password is incorrect'];
        }

        $roleData = $this->getUserRole($user->id);
        if (
            ($type === 'user' && $roleData['roleId'] === ModelHasRoles::ADMIN) ||
            ($type === 'admin' && $roleData['roleId'] !== ModelHasRoles::ADMIN)
        ) {
            return [null, null, 'This account is not allowed to log in.'];
        }

        return [$user, $roleData, null];
    }
    /**
     * Get billing invoice user details with their first subscribed resource.
     *
     * @param int $billingInvoiceId
     *
     * @return array
     */
    private function billingInvoice($billingInvoiceId)
    {
        $data = [];

        $billingUsers = BillingInvoiceUsers::where('billing_invoice_id', $billingInvoiceId)->get();
        $billingInvoice = BillingEmail::find($billingInvoiceId);

        foreach ($billingUsers as $billingUser) {
            $user = $this->getUserById($billingUser->user_id);
            $subscription = $this->getLatestSubscriptionForUser($billingUser->user_id);
            $resource = $this->getResourceFromSubscription($subscription);

            $data[] = [
                'user_email' => $user?->email,
                'resource_name' => $resource?->resource_name ?? null,
                'amount' => $resource?->annaul_fee ?? 0,
            ];
        }
        if (! empty($data) && $billingInvoice?->invoice_email) {
            InvoiceEmailJob::dispatch($data, $billingInvoice->invoice_email);
        }
    }
    /**
     * Get a user by ID.
     *
     * @param int $userId
     *
     * @return User|null
     */
    private function getUserById(int $userId): ?User
    {
        return User::find($userId);
    }
    /**
     * Get the latest subscription entry for a given user.
     *
     * @param int $userId
     *
     * @return UserSubscription|null
     */
    private function getLatestSubscriptionForUser(int $userId): ?UserSubscription
    {
        return UserSubscription::where('user_id', $userId)
            ->where('latest_subscription', UserSubscription::STATUS_ONE)
            ->orderByDesc('id')
            ->first();
    }
    /**
     * Get the learning resource associated with a subscription.
     *
     * @param UserSubscription|null $subscription
     *
     * @return Resources|null
     */
    private function getResourceFromSubscription(?UserSubscription $subscription): ?Resources
    {
        return $subscription ? Resources::find($subscription->resource_id) : null;
    }
}
