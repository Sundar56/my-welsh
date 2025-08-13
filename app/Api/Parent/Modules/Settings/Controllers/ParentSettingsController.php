<?php

namespace App\Api\Parent\Modules\Settings\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\UserSettingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentSettingsController extends BaseController
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
     * Handle the user's profile details for parent
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewProfileByParent(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);
        $roleId = $this->getRoleIdFromToken($request);
        $lang = $request->query('language', 'en');

        return $this->handleServiceResponse(
            $this->userSettingServcie->userViewProfile($userId, $roleId, $lang)
        );
    }
    /**
     * Update the user's profile information for parent
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function editProfileByParent(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);
        $roleId = $this->getRoleIdFromToken($request);

        return $this->handleServiceResponse(
            $this->userSettingServcie->userEditProfile($request, $userId, $roleId)
        );
    }
}
