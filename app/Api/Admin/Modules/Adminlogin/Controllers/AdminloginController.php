<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Adminlogin\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\LoginService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminloginController extends BaseController
{
    use ApiResponse;

    protected $loginService;

    public function __construct(LoginService $loginService)
    {
        $this->loginService = $loginService;
    }

    /**
     * Handle Admin login.
     *
     * This method attempts to authenticate the user using the provided credentials.
     * It wraps the login process in a database transaction to ensure atomicity in case
     * of any additional user logging or side effects that need to be rolled back on failure.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminLogin(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->loginService->login($request, 'admin')
        );
    }
    /**
     * Handle the Forgot Password request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminForgotPassword(Request $request): JsonResponse
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
    public function adminLogout(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->loginService->logout($request)
        );
    }
}
