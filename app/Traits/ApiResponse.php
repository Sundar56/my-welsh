<?php

namespace App\Traits;

use App\Jobs\SendEmailJob;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
        return $appUrl . '/' . ltrim($logo, '/');
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
        return rtrim($frontendUrl, '/') . '/' . ltrim($path, '/');
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
    protected function validateRequest(array $data, array $rules, array $messages = []): ?array
    {
        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return null;
    }
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
    protected function getUserIdFromToken(Request $request): int
    {
        return $request->attributes->get('decoded_token')->get('id');
    }
    protected function getRoleIdFromToken(Request $request): int
    {
        return $request->attributes->get('decoded_token')->get('roleId');
    }
    /**
     * Dispatches an email job to send user credentials or notices.
     *
     * @param string $email
     * @param string|null $password
     * @param string $type
     *
     * @return void
     */
    private function sendUserEmail(string $email, ?string $password, string $type): void
    {
        SendEmailJob::dispatch($email, $password, $type);
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
}
