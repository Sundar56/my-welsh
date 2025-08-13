<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Parent\Modules\Signup\Models\ParentPlaylists;
use App\Api\Parent\Modules\Signup\Models\TeacherInvites;
use App\Models\ModelHasRoles;
use App\Models\User;
use App\Models\UserSubscription;
use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ParentLoginService
{
    use ApiResponse, TransactionWrapper;

    protected DataSecurityService $dataSecurityService;
    protected LoginService $loginService;

    public function __construct(DataSecurityService $dataSecurityService, LoginService $loginService)
    {
        $this->dataSecurityService = $dataSecurityService;
        $this->loginService = $loginService;
    }
    /**
     * Handle parent signup / login and assign a role.
     *
     * @param Request $request
     *
     * @return array
     */
    public function parentSignupOrLogin(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $lang = $request->language ?? 'en';
            $user = $this->getUserByEmail($request->username);

            if ($user !== null) {
                $this->storeParentPlaylist($request, $user->id);
            }

            return match (true) {
                $user !== null => $this->loginService->login($request, 'parent'),
                default => $this->parentSignup($request, $lang),
            };
        });
    }
    /**
     * Handle the signup process for a parent user.
     *
     * @param Request $request The HTTP request containing signup data.
     * @param string  $lang    The language code for localized responses.
     *
     * @return array The response data indicating success or failure.
     */
    private function parentSignup(Request $request, string $lang): array
    {
        $validationErrors = $this->loginService->validateSignupForCard($request, ModelHasRoles::PARENT);
        if ($validationErrors) {
            return $this->validationErrorResponse($validationErrors);
        }
        $expiryCheck = $this->urlExpiry($request);
        if ($expiryCheck) {
            return $expiryCheck;
        }
        $roleId = $request->user_type;
        $user = $this->createParentRequest($request->all());
        $this->assignRoleToUser($user, (int) $roleId);
        $this->storeParentPlaylist($request, $user->id);
        $roleData = $this->loginService->getUserRole($user->id);
        $token = $this->loginService->generateToken($user, $roleData['roleId'] ?? '');
        $userData = $this->loginService->buildUserDataArray($user, $roleData, $token);
        $this->loginService->handleLoginHistory($user, $request, $roleData);
        $this->sendUserEmail($user->email, null, 'activate', $lang, null);
        // $encryptedUserData = $this->encryptUserData($userData);
        return $this->successResponse($userData, trans('message.success.register', [], $lang));
    }
    /**
     * Creates a user from signup request and subscription details.
     *
     * @param Request $request The HTTP request with user info
     */
    private function createParentRequest(array $data)
    {
        $name = Str::title(str_replace(['.', '_'], ' ', Str::before($data['username'], '@')));

        return User::create([
            'email' => $data['username'],
            'password' => Hash::make($data['password']),
            'payment_type' => UserSubscription::PARENT,
            'language_code' => $this->getLanguageCodeValue($data['language']),
            'is_subscribed' => UserSubscription::STATUS_ZERO,
            'is_trail' => UserSubscription::STATUS_ZERO,
            'name' => $name ?? '',
            'is_customer' => $data['isCustomer'] ?? UserSubscription::STATUS_ZERO,
            'is_activated' => UserSubscription::STATUS_ONE,
        ]);
    }
    /**
     * Store parent playlist data based on the request for a specific user.
     *
     * @param Request $request The HTTP request containing playlist data.
     * @param int     $userId  The ID of the parent user.
     *
     * @return void
     */
    private function storeParentPlaylist(Request $request, int $userId)
    {
        $teacherId = $this->decryptedValues($request->teacher_id);
        $this->storeTeacherInvites($teacherId, $userId);
        $playlistId = $this->decryptedValues($request->playlist_id);
        $this->storePlaylistId($playlistId, $userId);
    }
    /**
     * Store teacher invite information for a given user.
     *
     * @param int $teacherId The ID of the invited teacher.
     * @param int $userId    The ID of the user who is inviting the teacher.
     *
     * @return void
     */
    private function storeTeacherInvites(int $teacherId, int $userId)
    {
        TeacherInvites::create([
            'teacher_id' => $teacherId,
            'parent_id' => $userId,
            'status' => UserSubscription::STATUS_ZERO,
        ]);
    }
    /**
     * Store the playlist ID for the given user.
     *
     * @param int $playlistId The ID of the playlist to associate.
     * @param int $userId     The ID of the user to associate the playlist with.
     *
     * @return void
     */
    private function storePlaylistId(int $playlistId, int $userId)
    {
        ParentPlaylists::create([
            'parent_id' => $userId,
            'playlist_id' => $playlistId,
            'status' => UserSubscription::STATUS_ZERO,
        ]);
    }
    /**
     * Validate whether the provided URL expiry timestamp is still valid.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming request containing the expiry parameter and language.
     *
     * @return array Returns an empty array if valid, or an error response array if expired.
     */
    private function urlExpiry(Request $request): array
    {
        $expiry = $this->decryptedValues($request->exp);
        $lang = $request->language;
        if (now()->timestamp > $expiry) {
            return $this->failedResponse(trans('message.errors.link_expired', [], $lang));
        }
        return [];
    }
}
