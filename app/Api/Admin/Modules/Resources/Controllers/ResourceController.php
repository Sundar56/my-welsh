<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Resources\Controllers;

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
    public function addResource(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->resourceService->addResource($request)
        );
    }
    /**
     * Handle add resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resourceInfo(Request $request): JsonResponse
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
    public function getResources(): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->subscriptionService->trailResourceList()
        );
    }
    /**
     * Handle add resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function editResource(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->resourceService->editResource($request)
        );
    }
    /**
     * Handle delete resource topic.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminDeleteTopic(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->resourceService->deleteTopic($request)
        );
    }
    /**
     * Handle get All modules resources list.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function alModulesWithTopics(Request $request): JsonResponse
    {
        $userId = $this->getUserIdFromToken($request);

        return $this->handleServiceResponse(
            $this->resourceService->allModulesList($userId)
        );
    }
}
