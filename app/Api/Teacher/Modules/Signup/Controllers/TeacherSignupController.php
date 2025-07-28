<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Signup\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\LoginService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherSignupController extends BaseController
{
    use ApiResponse;

    protected $loginService;

    public function __construct(LoginService $loginService)
    {
        $this->loginService = $loginService;
    }

    /**
     * Handle user signup and assign a role.
     *
     * This method creates a new user and assigns a role based on the user_type.
     * All operations are wrapped in a database transaction to ensure atomicity.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userSignup(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->loginService->userSignup($request)
        );
    }
}
