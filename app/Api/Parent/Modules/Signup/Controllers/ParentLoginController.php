<?php

declare(strict_types=1);

namespace App\Api\Parent\Modules\Signup\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\LoginService;
use App\Services\ParentLoginService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentLoginController extends BaseController
{
    use ApiResponse;

    protected $parentLoginService;
    protected $loginService;

    public function __construct(ParentLoginService $parentLoginService, LoginService $loginService)
    {
        $this->parentLoginService = $parentLoginService;
    }

    /**
     * Handle user signup / login and assign a role for parent
     *
     * This method creates a new user and assigns a role based on the user_type.
     * All operations are wrapped in a database transaction to ensure atomicity.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function parentSignupOrLogin(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->parentLoginService->parentSignupOrLogin($request)
        );
    }
    /**
     * Handle the forgot password request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function parentForgotPassword(Request $request)
    {
        return $this->handleServiceResponse(
            $this->loginService->forgotPassword($request)
        );
    }
    /**
     * Handle the logout request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function parentLogout(Request $request)
    {
        return $this->handleServiceResponse(
            $this->loginService->logout($request)
        );
    }
}
