<?php

namespace App\Http\Controllers\Api;

use App\Services\LanguageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguageController extends BaseController
{
    use ApiResponse;

    /**
     * @var LanguageService
     */
    protected $languageService;

    public function __construct(LanguageService $languageService)
    {
        $this->languageService = $languageService;
    }

    /**
     * Handle user get Languages.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLanguages(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->languageService->getLanguages($request)
        );
    }
}
