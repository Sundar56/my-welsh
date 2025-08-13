<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Settings\Models\Settings;
use App\Api\Admin\Modules\Settings\Models\UpdateSettingHistory;
use App\Models\ModelHasRoles;
use App\Models\TrailHistory;
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
    protected UploadFileService $uploadFileService;

    public function __construct(DataSecurityService $dataSecurityService, CustomerService $customerService, UploadFileService $uploadFileService)
    {
        $this->dataSecurityService = $dataSecurityService;
        $this->customerService = $customerService;
        $this->uploadFileService = $uploadFileService;
    }
    /**
     * Display the user's profile details.
     *
     * @param int $userId Optional user ID to fetch specific resources.
     * @param int $roleId Optional role ID to fetch specific resources.
     * @param string $lang Dynamic languages with 'en' and 'cy'.
     *
     * @return array
     */
    public function userViewProfile(int $userId, int $roleId, string $lang = 'en'): array
    {
        return $this->runInTransaction(function () use ($userId, $roleId, $lang) {
            $user = $this->getUserById($userId);
            if (! $user) {
                return $this->failedResponse(trans('message.login_val.username.exists', [], $lang));
            }

            $data = match ($roleId) {
                ModelHasRoles::ADMIN, ModelHasRoles::PARENT => $this->getAdminOrParentProfile($user->email),
                ModelHasRoles::TEACHER => $this->getTeacherProfile($userId, $user->email),
                default => [],
            };

            return $this->successResponse($data, trans('message.success.user_settings_details', [], $lang));
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
            if ($roleId === ModelHasRoles::TEACHER && $request->resource_id !== null) {
                $this->handleSubscription($request, $userId);
            }
            $lang = $request->language ?? 'en';

            return $this->successResponse(null, trans('message.success.user_settings_updated', [], $lang));
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
        $lang = $request->language ?? 'en';
        $user = User::find($userId);

        $error = $this->validatePasswordChange($user, $oldPassword, $newPassword, $repeatPassword, $lang);
        if ($error) {
            return $this->failedResponse($error);
        }

        $user->password = $newPassword;
        $user->save();

        return $this->successResponse(null, trans('message.success.user_password_updated', [], $lang));
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
            $lang = $request->language ?? 'en';
            $user = $this->getUserById($userId);
            $user->is_cancelled = $request->is_cancelled;
            $user->save();

            $this->sendUserEmail($user->email, null, 'cancelled', $lang, $userId);

            return $this->successResponse(null, trans('message.success.subscription_cancelled', [], $lang));
        });
    }
    /**
     * Create admin settings information.
     *
     * @param Request $request
     * @param int $userId Optional user ID to fetch specific admin settings.
     *
     * @return array
     */
    public function createSettings(Request $request, int $userId): array
    {
        return $this->runInTransaction(function () use ($request, $userId) {
            $lang = $request->language ?? 'en';
            if (! $this->isAdminUser($userId)) {
                return $this->failedResponse(trans('message.errors.admin_setting_val', [], $lang));
            }

            $settings = $this->storeAdminSettings($request, $userId);
            $this->uploadFileService->uploadLogoImage($request, $settings['id']);

            return $this->successResponse(null, trans('message.success.setting_success', [], $lang));
        });
    }
    /**
     * View admin settings information.
     *
     * @param Request $request
     * @param int $userId Optional user ID to fetch specific resources.
     *
     * @return array
     */
    public function viewAdminSettings(Request $request, int $userId): array
    {
        return $this->runInTransaction(function () use ($request, $userId) {
            $lang = $request->language ?? 'en';
            if (! $this->isAdminUser($userId)) {
                return $this->failedResponse(trans('message.errors.admin_setting_val', [], $lang));
            }

            $settings = Settings::where('user_id', $userId)->first();
            if (! $settings) {
                return $this->failedResponse('Settings not found for user ID');
            }
            $data = $this->formatSettingsData($settings);

            return $this->successResponse($data, trans('message.success.setting_success', [], $lang));
        });
    }

    /**
     * Edit admin settings information.
     *
     * @param Request $request
     * @param int $userId Optional user ID to fetch specific admin settings.
     *
     * @return array
     */
    public function editSettings(Request $request, int $userId): array
    {
        return $this->runInTransaction(function () use ($request, $userId) {
            $lang = $request->language ?? 'en';
            if (! $this->isAdminUser($userId)) {
                return $this->failedResponse(trans('message.errors.admin_setting_val', [], $lang));
            }
            $settingsId = $this->decryptedValues($request->setting_id);
            $settingsData = Settings::where('id', $settingsId)->first();
            $previousRecordJson = $settingsData ? json_encode($settingsData->toArray()) : json_encode([]);
            $updatedSettings = $this->updateAdminSettings($request, $settingsId);
            $this->uploadFileService->uploadLogoImage($request, $settingsId);
            $updatedRecordJson = json_encode($updatedSettings->toArray());
            $this->updateSettingHistory($request, $userId, $previousRecordJson, $updatedRecordJson);

            return $this->successResponse(null, trans('message.success.setting_success', [], $lang));
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
    protected function validatePasswordChange($user, $oldPassword, $newPassword, $repeatPassword, string $lang = 'en'): ?string
    {
        $checks = [
            [! $oldPassword || ! $newPassword, trans('message.errors.password_required_any', [], $lang)],
            [$newPassword !== $repeatPassword, trans('message.errors.password_mismatch', [], $lang)],
            [! $user || ! Hash::check($oldPassword, $user->password), trans('message.errors.old_password_invalid', [], $lang)],
            [$oldPassword === $newPassword, trans('message.errors.password_same_as_old', [], $lang)],
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
        $type = $this->customerService->storeUserSubscription($resourceId, $userId);
        $user = $this->getUserById($userId);
        if ($user->is_trail === 1 && $resourceId) {
            $this->updateTrailSubscription($userId, $resourceId);
            return;
        }
        $this->buildSubscriptionHistory($type['id'], $resourceId);
    }
    /**
     * Format the settings data into a structured array with encrypted ID.
     *
     * @param App\Api\Admin\Modules\Settings\Models\Settings $settings The settings model instance.
     *
     * @return array The formatted settings data.
     */
    protected function formatSettingsData(\App\Api\Admin\Modules\Settings\Models\Settings $settings): array
    {
        return [
            'apiKey' => $settings->apikey,
            'apiSecret' => $settings->apisecret,
            'webhookKey' => $settings->webhookkey,
            'webhookUrl' => $settings->webhookurl,
            'fixedFee' => $settings->fixedfee,
            'percentageFee' => $settings->percentagefee,
            'title' => $settings->title,
            'description' => $settings->description,
            'keyword' => $settings->keyword,
            'logo' => $settings->logo,
            'encryptedId' => $this->encryptedValues($settings->id),
        ];
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
        $user = User::where('id', $userId)->select('is_trail')->first();

        if ($user?->is_trail === UserSubscription::STATUS_ONE) {
            return $this->getTrialTeacherProfile($userId, $userEmail);
        }

        $subscriptionData = $this->getLatestTeacherSubscription($userId);
        $formattedAmount = '£' . number_format((float) $subscriptionData->amount, 2);
        $renewalDate = \Carbon\Carbon::parse($subscriptionData->endDate)->format('d/m/Y');

        return [
            'emailAddress' => $userEmail,
            'subscriptionFee' => $formattedAmount,
            'resourceName' => $subscriptionData->resourceName,
            'renewalDate' => $renewalDate,
        ];
    }
    /**
     * Get teacher profile information for a user on trial.
     *
     * @param int $userId
     * @param string $userEmail
     *
     * @return array
     */
    private function getTrialTeacherProfile(int $userId, string $userEmail): array
    {
        $trialData = $this->getTrialInfo($userId);
        $renewalDate = \Carbon\Carbon::parse($trialData->trailEndDate)->format('d/m/Y');

        return [
            'emailAddress' => $userEmail,
            'subscriptionFee' => 'Free',
            'resourceName' => $trialData->resourceName ?? 'Free 7 Day Trial',
            'renewalDate' => $renewalDate ?? '7 Days',
        ];
    }
    /**
     * Retrieve trial resource information for the specified user.
     *
     * @param int $userId The ID of the user.
     *
     * @return object|null The trial resource data or null if not found.
     */
    private function getTrialInfo(int $userId): ?object
    {
        return DB::table('trail_history')
            ->leftJoin('learning_resources', 'trail_history.resource_id', '=', 'learning_resources.id')
            ->where('trail_history.user_id', $userId)
            ->select(
                'learning_resources.resource_name as resourceName',
                'trail_history.trail_end_date as trailEndDate'
            )
            ->first();
    }
    /**
     * Retrieve trial resource information for the specified user.
     *
     * @param int $userId The ID of the user.
     * @param int $resourceId The resourceId of the user.
     *
     * @return void
     */
    private function updateTrailSubscription(int $userId, int $resourceId): void
    {
        TrailHistory::where('user_id', $userId)->update([
            'resource_id' => $resourceId,
        ]);
    }
    /**
     * Store admin-specific settings from the given request.
     *
     * @param Request $request The HTTP request containing admin settings data.
     * @param int $userId Optional user ID to fetch specific admin settings.
     *
     * @return array Response data indicating success or failure.
     */
    private function storeAdminSettings(Request $request, int $userId): array
    {
        $settings = Settings::create([
            'user_id' => $userId,
            'apikey' => $request->apikey,
            'apisecret' => $request->apisecret,
            'webhookkey' => $request->webhookkey,
            'webhookurl' => $request->webhookurl,
            'fixedfee' => $request->fixedfee,
            'percentagefee' => $request->percentagefee,
            'title' => $request->title,
            'description' => $request->description,
            'keyword' => $request->keyword,
        ]);

        return [
            'id' => $settings->id,
        ];
    }
    /**
     * Update admin-specific settings from the given request.
     *
     * @param Request $request The HTTP request containing admin settings data.
     * @param int $settingsId Optional settings ID to fetch specific admin settings.
     *
     * @return App\Api\Admin\Modules\Settings\Models\Settings The updated settings model.
     */
    private function updateAdminSettings(Request $request, int $settingsId): \App\Api\Admin\Modules\Settings\Models\Settings
    {
        $settings = Settings::findOrFail($settingsId);

        $settings->update([
            'apikey' => $request->apikey,
            'apisecret' => $request->apisecret,
            'webhookkey' => $request->webhookkey,
            'webhookurl' => $request->webhookurl,
            'fixedfee' => $request->fixedfee,
            'percentagefee' => $request->percentagefee,
            'title' => $request->title,
            'description' => $request->description,
            'keyword' => $request->keyword,
        ]);

        return $settings->fresh();
    }
    private function updateSettingHistory($request, $userId, $previousRecordJson, $updatedRecordJson)
    {
        UpdateSettingHistory::create([
            'updated_by' => $userId,
            'previous_record' => $previousRecordJson,
            'updated_record' => $updatedRecordJson,
            'updated_time' => now(),
            'ipaddress' => $request->ip(),
            'useragent' => $request->userAgent(),
        ]);
    }
}
