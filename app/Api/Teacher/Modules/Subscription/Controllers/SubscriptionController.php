<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Subscription\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseController
{
    use ApiResponse;

    /**
     * @var SubscriptionService
     */
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }
    /**
     * Handle trail resources list (When a trail is selected, the list of trail resources will be displayed)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function trailResourceList(): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->subscriptionService->trailResourceList()
        );
    }
    /**
     * Handle Update trail details.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTrailData(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->subscriptionService->updateTrailData($request)
        );
    }
    /**
     * Handle subscription resources list.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubscriptionList(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->subscriptionService->getSubscriptionList($request)
        );
    }
}
