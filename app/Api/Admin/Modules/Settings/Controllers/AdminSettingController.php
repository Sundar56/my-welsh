<?php

namespace App\Api\Admin\Modules\Settings\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\UserSettingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingController extends BaseController
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
    public function adminViewProfile(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);
        $roleId = $this->getRoleIdFromToken($request);

        return $this->handleServiceResponse(
            $this->userSettingServcie->userViewProfile($userId, $roleId)
        );
    }
    /**
     * Update the user's profile information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminEditProfile(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);
        $roleId = $this->getRoleIdFromToken($request);

        return $this->handleServiceResponse(
            $this->userSettingServcie->userEditProfile($request, $userId, $roleId)
        );
    }
}
