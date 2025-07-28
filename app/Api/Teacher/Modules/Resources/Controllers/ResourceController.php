<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Resources\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\ResourceService;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends BaseController
{
    use ApiResponse;

    /**
     * @var ResourceService
     */
    protected $resourceService;
    /**
     * @var SubscriptionService
     */
    protected $subscriptionService;

    public function __construct(ResourceService $resourceService, SubscriptionService $subscriptionService)
    {
        $this->resourceService = $resourceService;
        $this->subscriptionService = $subscriptionService;
    }
    /**
     * Handle add resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewResourceInfo(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->resourceService->resourceInfo($request)
        );
    }
    /**
     * Handle get resources list.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resourceList(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);

        return $this->handleServiceResponse(
            $this->subscriptionService->trailResourceList($userId)
        );
    }
    /**
     * Handle get All modules resources list.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function allModulesList(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);

        return $this->handleServiceResponse(
            $this->resourceService->allModulesList($userId)
        );
    }
}
