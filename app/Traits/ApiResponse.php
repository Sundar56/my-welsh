<?php

namespace App\Traits;

use App\Api\Admin\Modules\Resources\Models\Resources;
use App\Jobs\SendEmailJob;
use App\Models\Languages;
use App\Models\ModelHasRoles;
use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

trait ApiResponse
{
    /**
     * Decrypt the given encrypted ID and return the original value.
     *
     * @param string $encryptedId The encrypted ID.
     *
     * @return int|string The decrypted (original) ID.
     */
    public function decryptedValues(string $encryptedId): int|string
    {
        return Crypt::decrypt($encryptedId);
    }
    /**
     * Encrypt the given ID and return the encrypted string.
     *
     * @param int|string $id The plain ID to encrypt.
     *
     * @return string The encrypted ID.
     */
    public function encryptedValues($id): string
    {
        return Crypt::encrypt($id);
    }
    /**
     * Store a new subscription record for the given user and resource.
     *
     * @param int    $resourceId  The decrypted resource ID associated with the subscription.
     * @param int    $userId   The ID of the user for whom the subscription and trial are handled.
     *
     * @return array
     */
    public function storeUserSubscription(int $resourceId, int $userId): array
    {
        $typeId = UserSubscription::create([
            'user_id' => $userId,
            'resource_id' => $resourceId,
            'status' => UserSubscription::STATUS_ZERO,
            'latest_subscription' => UserSubscription::STATUS_ONE,
        ]);

        return [
            'id' => $typeId->id,
        ];
    }
    /**
     * Build or log the trail history for a given user and resource.
     *
     * @param int $userId The ID of the user.
     * @param int $resourceId The ID of the resource.
     *
     * @return void
     */
    public function buildSubscriptionHistory(int $typeId, int $resourceId): void
    {
        $resource = Resources::where('id', $resourceId)->first();
        $startDate = now();
        $endDate = $startDate->copy()->addYear();
        SubscriptionHistory::create([
            'type_id' => $typeId,
            'subscription_amount' => $resource->annual_fee,
            'subscription_start_date' => $startDate,
            'subscription_end_date' => $endDate,
            'fee_type' => SubscriptionHistory::ANNUAL_FEE,
        ]);
    }
    /**
     * Convert an image to a base64-encoded string.
     *
     * @param string $relativePath Relative path from public/ folder (e.g., 'assets/images/logo.png')
     *
     * @return string|null Base64 encoded image string or null if file doesn't exist
     */
    public function getBase64Image(string $relativePath): ?string
    {
        $imagePath = public_path($relativePath);

        if (! file_exists($imagePath)) {
            return null;
        }

        $type = pathinfo($imagePath, PATHINFO_EXTENSION);
        $data = file_get_contents($imagePath);

        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    /**
     * Get the full URL path of the application logo.
     *
     * This method retrieves the logo path from the environment variable `CPS_LOGO`
     * and prepends the application base URL defined in `APP_URL`.
     *
     * @return string The fully qualified URL to the logo image.
     */
    public function getLogoPath(): string
    {
        $logo = env('FFALALA_LOGO');
        $appUrl = env('APP_URL');

        return $appUrl . '/' . $logo;
    }
    /**
     * Get the full redirect URL by appending a path to FRONT_END_URL
     *
     * @param string $path
     *
     * @return string
     */
    public function getRedirectUrl(string $path): string
    {
        $frontendUrl = env('FRONT_END_URL');

        return $frontendUrl . '/' . $path;
    }
    /**
     * Format a standardized success response array.
     *
     * @param mixed  $data
     * @param string $message
     * @param int    $statusCode
     *
     * @return array The formatted success response.
     */
    protected function successResponse($data, $message, $statusCode = 200): array
    {
        return [
            'status' => true,
            'data' => $data,
            'message' => $message,
            'statusCode' => $statusCode,
        ];
    }
    /**
     * Returns a formatted error response.
     *
     * @param string $errorMessage
     *
     * @return array
     */
    protected function errorResponse($message, $statusCode = 500): array
    {
        return [
            'status' => false,
            'data' => [],
            'message' => $message,
            'statusCode' => $statusCode,
        ];
    }
    /**
     * Handle standard API service responses.
     *
     * @param array $response
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleServiceResponse(array $response): \Illuminate\Http\JsonResponse
    {
        if ($response['status']) {
            return $this->sendResponse($response['data'], $response['message']);
        }

        return $this->sendError(
            $response['message'],
            $response['errors'] ?? [],
            $response['statusCode'] ?? 400
        );
    }
    /**
     * Validates the given data against the specified rules and returns errors if validation fails.
     *
     * @param array $data     The data to validate.
     * @param array $rules    The validation rules to apply.
     * @param array $messages Optional custom validation error messages.
     *
     * @return array|null An array of validation errors, or null if validation passes.
     */

    protected function validateRequest(array $data, array $rules, array $messages = []): ?array
    {
        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return null;
    }
    /**
     * Formats and returns a standardized validation error response.
     *
     * @param mixed $errors The validation errors to include in the response.
     *
     * @return array An array containing the formatted validation error response.
     */
    protected function validationErrorResponse($errors): array
    {
        return [
            'status' => false,
            'message' => 'Validation Error.',
            'errors' => $errors,
            'statusCode' => 400,
        ];
    }
    /**
     * Returns a formatted error response for login.
     *
     * @return array
     */
    protected function failedResponse(string $message): array
    {
        return [
            'status' => false,
            'message' => $message,
            'errors' => ['error' => [$message]],
            'statusCode' => 400,
        ];
    }
    /**
     * Extracts the user ID from the authenticated user's token.
     *
     * @param Request $request The current HTTP request containing the token.
     *
     * @return int The user ID extracted from the token.
     */
    protected function getUserIdFromToken(Request $request): int
    {
        return $request->attributes->get('decoded_token')->get('id');
    }
    /**
     * Extracts the role ID from the authenticated user's token.
     *
     * @param Request $request The current HTTP request containing the token.
     *
     * @return int The role ID extracted from the token.
     */

    protected function getRoleIdFromToken(Request $request): int
    {
        return $request->attributes->get('decoded_token')->get('roleId');
    }
    /**
     * Checks if the given user has the ADMIN role.
     *
     * @param int $userId
     *
     * @return bool
     */
    protected function isAdminUser(int $userId): bool
    {
        return ModelHasRoles::where('model_id', $userId)
            ->where('role_id', ModelHasRoles::ADMIN)
            ->exists();
    }
    /**
     * Dispatches an email job to send user credentials or notices.
     *
     * @param string $email
     * @param string|null $password
     * @param string $type
     * @param string $lang
     * @param int|null $userId
     *
     * @return void
     */
    private function sendUserEmail(string $email, ?string $password, string $type, string $lang, ?int $userId): void
    {
        SendEmailJob::dispatch($email, $password, $type, $lang, $userId);
    }
    /**
     * Assign a role to a user based on role ID and type.
     *
     * @param \App\Models\User $user The user instance to assign the role to.
     * @param int $roleId The ID of the role to be assigned.
     *
     * @return void
     */
    private function assignRoleToUser(User $user, int $roleId): void
    {
        $role = Role::where('id', $roleId)
            ->where('type', 1)
            ->first();

        if ($role) {
            $user->assignRole($role->name);
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
     * Maps a language code to its corresponding integer value.
     *
     * @param string $languageCode The language code to map (e.g. 'en', 'cy').
     *
     * @return int|null The corresponding integer value for the language code, or null if not found.
     */
    private function getLanguageCodeValue(string $languageCode): ?int
    {
        $language_map = [
            'en' => Languages::ENGLISH,
            'cy' => Languages::WELSH,
        ];

        return $language_map[$languageCode] ?? null;
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
    /**
     * Retrieve a user by email with limited fields.
     *
     * @param  string  $email
     *
     * @return \App\Models\User|null  Returns the user instance or null if not found.
     */
    private function getUserByEmail(string $email): ?User
    {
        return User::select('id', 'email', 'password', 'name', 'is_activated')
            ->where('email', $email)
            ->first();
    }
    /**
     * Get all settings for the superadmin user.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getSuperadminSettings()
    {
        $roleId = DB::table('roles')
            ->where('name', 'superadmin')
            ->value('id');

        $userId = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->value('model_id');

        return DB::table('settings')
            ->where('user_id', $userId)
            ->pluck('value', 'key');
    }
    /**
     * Set the Stripe API key from the superadmin settings table.
     *
     * This method attempts to fetch the Stripe secret key from the superadmin's settings.
     * If the key is not found in the database, it falls back to the value in config('services.stripe.secret').
     *
     * @return void
     */
    public function setStripeApiKey(): void
    {
        $settings = $this->getSuperadminSettings();
        $apiSecret = $settings['apisecret'] ?? config('services.stripe.secret');
        \Stripe\Stripe::setApiKey($apiSecret);
    }
}
