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
                : $this->validateSignupForCard($request, ModelHasRoles::TEACHER);
            if ($validationErrors) {
                return $this->validationErrorResponse($validationErrors);
            }
            $request->payment_type === UserSubscription::BACS ? $this->createUserForBacs($request) : $this->createUserForCard($request);
            $lang = $request->language ?? 'en';

            return $this->successResponse(null, trans('message.success.register', [], $lang));
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
            $trailResourceId = $this->decryptedValues($request->trail_resource_id);
            $this->subscriptionService->buildTrailHistory($user->id, $trailResourceId);
        } else {
            $this->buildSubscriptionHistory($type['id'], $resourceId);
            $this->stripeService->createCustomers($request, $user->id);
        }

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
        $invoiceEmail = trim($request->invoice_email);
        $lang = $request->language ?? 'en';
        if (in_array($invoiceEmail, $emails)) {
            return $this->failedResponse(trans('message.errors.invoice_email_conflict', [], $lang));
        }
        $resourceIds = is_array($request->resource_id)
            ? array_filter(array_map('trim', $request->resource_id))
            : [];
        if (count($emails) !== count($resourceIds)) {
            return $this->failedResponse(trans('message.errors.email_resource_mismatch', [], $lang));
        }
        $mappedData = array_map(null, $emails, $resourceIds);

        return $this->createAndAssignUsers($mappedData, $role, $billingInvoice->id, $lang);
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
            $lang = $request->language ?? 'en';
            return $this->successResponse($userData, trans('message.success.login', [], $lang));
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
            $lang = $request->language ?? 'en';
            $this->sendUserEmail($user->email, $newPassword, 'forgot', $lang, null);

            return $this->successResponse(null, trans('message.success.password_reset', [], $lang));
        });
    }
    /**
     * Handle user logout by invalidating the JWT token.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function logout(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            JWTAuth::invalidate(JWTAuth::getToken());

            $lang = $request->language ?? 'en';
            return $this->successResponse(null, trans('message.success.logout', [], $lang));
        });
    }
    /**
     * Creates a user from signup request and subscription details.
     *
     * @param Request $request The HTTP request with user info
     * @param bool $isSubscribed Whether the user is subscribed
     * @param bool $isTrail Whether the subscription is a trial
     */
    public function createUserFromRequest(array $data, bool $isSubscribed, bool $isTrail)
    {
        $name = Str::title(str_replace(['.', '_'], ' ', Str::before($data['email'], '@')));
        $isActivated = $isTrail
            ? UserSubscription::STATUS_ONE
            : ($data['isActivated'] ?? UserSubscription::STATUS_ZERO);

        return User::create([
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'payment_type' => $data['payment_type'] ?? null,
            'language_code' => $this->getLanguageCodeValue($data['language']),
            'is_subscribed' => $isSubscribed,
            'is_trail' => $isTrail,
            'name' => $name ?? '',
            'is_customer' => $data['isCustomer'] ?? UserSubscription::STATUS_ZERO,
            'is_activated' => $isActivated,
        ]);
    }
    /**
     * Retrieve the role ID and name for the given user ID.
     *
     * @param  int  $userId
     *
     * @return array  Returns an array with 'roleId' and 'roleName', or an empty array if no role is found.
     */
    public function getUserRole(int $userId): array
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
     * Validates the incoming request data for a general form submission.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request.
     *
     * @return \Illuminate\Contracts\Validation\Validator The validator instance with validation rules applied.
     */
    public function validate(Request $request)
    {
        $lang = $request->language ?? 'en';

        $rules = [
            'username' => 'required|email',
            'password' => 'required',
        ];

        $messages = trans('message.login_val', [], $lang);

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * @param Request $request
     * @param int $roleId
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validateSignupForCard(Request $request, int $roleId): ?array
    {
        $lang = $request->language ?? 'en';
        $rules = match ($roleId) {
            ModelHasRoles::TEACHER => $this->getSignupValidationRules(),
            ModelHasRoles::PARENT => $this->getParentSignupValidationRules(),
            default => [],
        };

        $messages = $this->getSignupValidationMessages($lang);

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * Get validation rules for signup.
     *
     * @return array
     */
    public function getSignupValidationRules(): array
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
     * Get validation rules for signup.
     *
     * @return array
     */
    public function getParentSignupValidationRules(): array
    {
        return [
            'username' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[@$!%*#?&]/',
            ],
        ];
    }
    /**
     * Validates the user and checks if the provided password matches.
     *
     * @param User|null $user The user object or null if not found.
     * @param string $password The plain text password to verify.
     *
     * @return bool True if user exists and password is valid, false otherwise.
     */
    public function isValidUser(?User $user, string $password): bool
    {
        return $user && $this->checkPassword($password, $user->password);
    }
    /**
     * Checks if the user's account is activated.
     *
     * @param User $user The user object to check.
     *
     * @return bool True if the account is activated, false otherwise.
     */
    public function isActivated(User $user): bool
    {
        return (int) $user->is_activated === 1;
    }
    /**
     * Check if the given role ID is authorized for the specified user type.
     *
     * @param string $type   The user type ('admin', 'user', 'parent', etc.)
     * @param int    $roleId The role ID to verify against the user type.
     *
     * @return bool True if the role ID is authorized for the given type, false otherwise.
     */
    public function isAuthorized(string $type, int $roleId): bool
    {
        return match ($type) {
            'admin' => $roleId === ModelHasRoles::ADMIN,
            'user' => in_array($roleId, [ModelHasRoles::TEACHER, ModelHasRoles::PARENT], true),
            'parent' => $roleId === ModelHasRoles::PARENT,
            default => false,
        };
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
    public function handleLoginHistory(User $user, Request $request, array $roleData): void
    {
        $this->updatePreviousLoginRecord($user, $roleData);
        $this->createLoginRecord($user, $request, $roleData);
    }
    /**
     * Generate JWT token for the authenticated user.
     *
     * @param User $user
     * @param string|int $roleId
     *
     * @return string
     */
    public function generateToken(User $user, string|int $roleId): string
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
    public function buildUserDataArray(User $user, array $roleData, string $token): array
    {
        $expIn = config('jwt.ttl') * 60;
        return [
            'userId' => $user->id,
            'email' => $user->email,
            'role' => $roleData['roleName'] ?? '',
            'roleId' => $roleData['roleId'] ?? '',
            'token' => $token,
            'exp_in' => $expIn,
            'isActivated' => $user->is_activated,
        ];
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
     * @param string $lang
     *
     * @return User|null  The last created user
     */
    private function createAndAssignUsers(array $mappedData, ?Role $role, int $billingInvoiceId, string $lang): ?User
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
            $this->sendUserEmail($email, $password, 'newuser', $lang, null);
            $lastUser = $user;
        }
        if ($billingInvoiceId) {
            InvoiceEmailJob::dispatch($billingInvoiceId, $lang);
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
    /**
     * Validates the request data for a forgot password request.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request containing email and other necessary data.
     *
     * @return \Illuminate\Contracts\Validation\Validator The validator instance with validation rules applied for password recovery.
     */
    private function validateForgotPassword(Request $request)
    {
        $lang = $request->language ?? 'en';

        $rules = [
            'username' => 'required|email|exists:users,email',
        ];

        $messages = trans('message.login_val', [], $lang);

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * Get custom validation messages for signup.
     *
     * @param string $lang language code
     *
     * @return array
     */
    private function getSignupValidationMessages(string $lang = 'en'): array
    {
        return trans('message.signup_val', [], $lang);
    }
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validateSignupforBacs(Request $request): ?array
    {
        $lang = $request->language ?? 'en';
        $rules = [
            'invoice_email' => 'required|email|unique:billing_emails,invoice_email',
            'school_email' => [
                'required',
                function ($attribute, $value, $fail) use ($lang) {
                    $this->validateSchoolEmails($value, $fail, $lang);
                },
            ],
        ];
        $messages = $this->getSignupValidationMessages($lang);

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * Validate a school email address and call the failure callback if invalid.
     *
     * @param string  $value The email address to validate.
     * @param callable $fail  A callback function to call if the validation fails.
     *
     * @return void
     */
    private function validateSchoolEmails(string $value, callable $fail, string $lang = 'en'): void
    {
        $emails = array_filter(array_map('trim', explode(',', $value)));
        if (count($emails) === 0) {
            $fail(trans('message.school_email_val.atlease_one', [], $lang));
            return;
        }
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fail(trans('message.school_email_val.invalid', ['email' => $email], $lang));
                return;
            }

            if (User::where('email', $email)->exists()) {
                $fail(trans('message.school_email_val.exists', ['email' => $email], $lang));
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
        $lang = $request->language ?? 'en';

        if (! $this->isValidUser($user, $request->password)) {
            return [null, null, trans('message.errors.invalid_credentials', [], $lang)];
        }

        if (! $this->isActivated($user)) {
            return [null, null, trans('message.errors.not_activated', [], $lang)];
        }

        $roleData = $this->getUserRole($user->id);

        if (! $this->isAuthorized($type, $roleData['roleId'])) {
            return [null, null, trans('message.errors.not_authorized', [], $lang)];
        }

        return [$user, $roleData, null];
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
