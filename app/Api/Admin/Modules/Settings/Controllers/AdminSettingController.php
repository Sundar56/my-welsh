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
        $lang = $request->query('language', 'en');

        return $this->handleServiceResponse(
            $this->userSettingServcie->userViewProfile($userId, $roleId, $lang)
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
    /**
     * Create the admin settings information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSettings(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);

        return $this->handleServiceResponse(
            $this->userSettingServcie->createSettings($request, $userId)
        );
    }
    /**
     * View the admin settings information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewAdminSettings(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);

        return $this->handleServiceResponse(
            $this->userSettingServcie->viewAdminSettings($request, $userId)
        );
    }
    /**
     * Edit the admin settings information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function editAdminSettings(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);

        return $this->handleServiceResponse(
            $this->userSettingServcie->editSettings($request, $userId)
        );
    }
}
