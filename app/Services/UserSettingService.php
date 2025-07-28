<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ModelHasRoles;
use App\Models\User;
use App\Models\UserSubscription;
use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSettingService
{
    use ApiResponse, TransactionWrapper;

    protected DataSecurityService $dataSecurityService;
    protected CustomerService $customerService;

    public function __construct(DataSecurityService $dataSecurityService, CustomerService $customerService)
    {
        $this->dataSecurityService = $dataSecurityService;
        $this->customerService = $customerService;
    }
    /**
     * Display the user's profile details.
     *
     * @param int $userId Optional user ID to fetch specific resources.
     * @param int $roleId Optional role ID to fetch specific resources.
     *
     * @return array
     */
    public function userViewProfile(int $userId, int $roleId): array
    {
        return $this->runInTransaction(function () use ($userId, $roleId) {
            $user = $this->getUserById($userId);
            if (! $user) {
                return $this->failedResponse('User not found');
            }
            if (in_array($roleId, [ModelHasRoles::ADMIN, ModelHasRoles::PARENT])) {
                $data = $this->getAdminOrParentProfile($user->email);
            }

            if ($roleId === ModelHasRoles::TEACHER) {
                $data = $this->getTeacherProfile($userId, $user->email);
            }

            return $this->successResponse($data, 'User settings details');
        });
    }
    /**
     * Update the user's profile information.
     *
     * @param Request $request
     * @param int $userId Optional user ID to fetch specific resources.
     * @param int $roleId Optional role ID to fetch specific resources.
     *
     * @return array
     */
    public function userEditProfile(Request $request, int $userId, int $roleId): array
    {
        return $this->runInTransaction(function () use ($request, $userId, $roleId) {
            $errors = $this->validateSettings($request, $userId);
            if ($errors) {
                return $this->validationErrorResponse($errors);
            }
            $this->handleEmailUpdate($request, $userId);
            $this->handlePasswordUpdate($request, $userId);
            if ($roleId === ModelHasRoles::TEACHER) {
                $this->handleSubscription($request, $userId);
            }

            return $this->successResponse(null, 'User settings updated');
        });
    }
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validateSettings(Request $request, int $userId): ?array
    {
        $rules = [
            'email' => 'required|email|unique:users,email,' . $userId . ',id',
        ];

        $messages = [
            'email.required' => 'Email is required',
            'email.email' => 'Enter a valid email',
        ];

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * Update the email address of the user.
     *
     * @param Request $request
     * @param int     $userId
     *
     * @return void
     */
    public function updateEmailAddress(Request $request, int $userId): void
    {
        $emailAddress = $request->email;
        User::where('id', $userId)->update([
            'email' => $emailAddress,
        ]);
    }
    /**
     * Update the user's password after validation.
     *
     * @param Request $request
     * @param int     $userId
     *
     * @return array
     */
    public function updatePassword(Request $request, int $userId): array
    {
        $oldPassword = $request->password;
        $newPassword = $request->new_password;
        $repeatPassword = $request->confirm_password;
        $user = User::find($userId);

        $error = $this->validatePasswordChange($user, $oldPassword, $newPassword, $repeatPassword);
        if ($error) {
            return $this->failedResponse($error);
        }

        $user->password = $newPassword;
        $user->save();

        return $this->successResponse(null, 'User settings updated');
    }
    /**
     * Handles cancel subscription for users
     *
     * @param Request $request
     *
     * @return array
     */
    public function cancelSubscription(Request $request): ?array
    {
        return $this->runInTransaction(function () use ($request) {
            $userId = $this->getUserIdFromToken($request);
            User::where('id', $userId)->update([
                'is_cancelled' => $request->is_cancelled,
            ]);

            return $this->successResponse(null, 'Subscription cancelled successflly');
        });
    }
    /**
     * Handles the update of a user's email address.
     *
     * @param Request $request  The HTTP request containing email data.
     * @param int $userId       The ID of the user whose email is being updated.
     *
     * @return void
     */
    protected function handleEmailUpdate(Request $request, int $userId): void
    {
        if ($request->email) {
            $this->updateEmailAddress($request, $userId);
        }
    }
    /**
     * Handles the update of a user's password.
     *
     * @param Request $request  The HTTP request containing password data.
     * @param int $userId       The ID of the user whose password is being updated.
     *
     * @return void
     */
    protected function handlePasswordUpdate(Request $request, int $userId): void
    {
        if (isset($request->newpassword) && $request->newpassword !== '') {
            $this->updatePassword($request, $userId);
        }
    }
    /**
     * Validates a user's password change request.
     *
     * @param mixed  $user            The user object or data being validated.
     * @param string $oldPassword     The current password.
     * @param string $newPassword     The new password.
     * @param string $repeatPassword  The repeated new password for confirmation.
     *
     * @return string|null            Returns an error message string if validation fails, or null on success.
     */
    protected function validatePasswordChange($user, $oldPassword, $newPassword, $repeatPassword): ?string
    {
        $checks = [
            [! $oldPassword || ! $newPassword, 'Old and new passwords are required.'],
            [$newPassword !== $repeatPassword, "Repeat Password doesn't match New Password"],
            [! $user || ! Hash::check($oldPassword, $user->password), "Old Password doesn't match!"],
            [$oldPassword === $newPassword, 'New Password cannot be the same as your current password'],
        ];

        foreach ($checks as [$condition, $message]) {
            if ($condition) {
                return $message;
            }
        }
        return null;
    }
    /**
     * Handles user subscription updates or creation.
     *
     * @param Request $request  The HTTP request containing subscription data.
     * @param int $userId       The ID of the user whose subscription is being managed.
     *
     * @return void
     */
    protected function handleSubscription(Request $request, int $userId): void
    {
        $resourceId = $this->decryptedValues($request->resource_id);
        UserSubscription::where('user_id', $userId)->update([
            'status' => UserSubscription::STATUS_ONE,
            'latest_subscription' => UserSubscription::STATUS_ZERO,
        ]);
        $this->customerService->storeUserSubscription($resourceId, $userId);
    }
    /**
     * Fetches the profile information (email) for Admin or Parent users.
     *
     * @param string $userEmail The email of the user.
     *
     * @return array The response array containing the user's email or an error message.
     */
    private function getAdminOrParentProfile(string $userEmail): array
    {
        return [
            'emailAddress' => $userEmail,
        ];
    }
    /**
     * Retrieves the profile information for a Teacher, including email and subscription details.
     *
     * @param int $userId The ID of the teacher.
     * @param string $userEmail The email of the user.
     *
     * @return array The response array containing email, subscription, and history data, or an error message.
     */
    private function getTeacherProfile(int $userId, string $userEmail): array
    {
        $subscriptionData = $this->getLatestTeacherSubscription($userId);
        $formattedAmount = '£' . number_format((float) $subscriptionData->amount, 2);
        $renewalDate = \Carbon\Carbon::parse($subscriptionData->endDate)->format('d/m/Y');
        $formattedRenewalDate = 'Renews on ' . $renewalDate;

        return [
            'emailAddress' => $userEmail,
            'subscriptionFee' => $formattedAmount,
            'resourceName' => $subscriptionData->resourceName,
            'renewalDate' => $formattedRenewalDate,
        ];
    }
    /**
     * Retrieves the user by ID, selecting only the email field.
     *
     * @param int $userId The ID of the user.
     *
     * @return User|null The User model instance if found, otherwise null.
     */
    private function getUserById(int $userId): ?User
    {
        return User::where('id', $userId)->select('email')->first();
    }
    /**
     * Retrieves the latest active teacher subscription with related subscription history using a left join.
     *
     * @param int $userId The ID of the teacher.
     *
     * @return object|null The subscription and history data object, or null if not found.
     */
    private function getLatestTeacherSubscription(int $userId): ?object
    {
        return DB::table('user_subscription')
            ->leftJoin('subscription_history', 'user_subscription.id', '=', 'subscription_history.type_id')
            ->leftJoin('learning_resources', 'user_subscription.resource_id', '=', 'learning_resources.id')
            ->where('user_subscription.user_id', $userId)
            ->where('user_subscription.latest_subscription', UserSubscription::STATUS_ONE)
            ->select(
                'subscription_history.subscription_amount as amount',
                'subscription_history.subscription_end_date as endDate',
                'learning_resources.resource_name as resourceName',
            )
            ->first();
    }
}
