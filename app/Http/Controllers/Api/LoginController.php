<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Languages;
use App\Services\LoginService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class LoginController extends BaseController
{
    use ApiResponse;

    protected $guard;

    /**
     * @var LoginService
     */
    protected $loginService;

    public function __construct(LoginService $loginService)
    {
        $this->guard = 'api';
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
    public function userSignup(Request $request)
    {
        return $this->handleServiceResponse(
            $this->loginService->userSignup($request)
        );
    }

    /**
     * Handle user login.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->loginService->login($request, 'user')
        );
    }

    /**
     * Handle the forgot password request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
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
    public function logout(Request $request)
    {
        return $this->handleServiceResponse(
            $this->loginService->logout($request)
        );
    }
    public function getLanguages()
    {
        // Redis::flushdb();
        // Redis::flushall();
        // Redis::del('languages');
        $cachedLanguages = Redis::get('languages');

        if (isset($cachedLanguages)) {
            $languages = json_decode($cachedLanguages, false);

            return response()->json([
                'status_code' => 201,
                'message' => 'Fetched from redis',
                'data' => $languages,
            ]);
        }
        $languages = Languages::get();
        Redis::set('languages', $languages);

        return response()->json([
            'status_code' => 201,
            'message' => 'Fetched from database',
            'data' => $languages,
        ]);
    }
}
