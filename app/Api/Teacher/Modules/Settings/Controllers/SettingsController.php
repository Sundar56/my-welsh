<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Settings\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\UserSettingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends BaseController
{
    use ApiResponse;
    /**
     * @var UserSettingService
     */
    protected $userSettingServcie;

    public function __construct(UserSettingService $userSettingServcie)
    {
        $this->userSettingServcie = $userSettingServcie;
    }
    /**
     * Handle the user's profile details.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewProfile(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);
        $roleId = $this->getRoleIdFromToken($request);
        $lang = $request->query('language', 'en');

        return $this->handleServiceResponse(
            $this->userSettingServcie->userViewProfile($userId, $roleId, $lang)
        );
    }
    /**
     * Update the user's profile information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function editProfile(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);
        $roleId = $this->getRoleIdFromToken($request);

        return $this->handleServiceResponse(
            $this->userSettingServcie->userEditProfile($request, $userId, $roleId)
        );
    }
    /**
     * Handle the user's profile details.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->userSettingServcie->cancelSubscription($request)
        );
    }
}
