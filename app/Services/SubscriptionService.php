<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Resources\Models\Resources;
use App\Models\SubscriptionHistory;
use App\Models\TrailHistory;
use App\Models\User;
use App\Models\UserSubscription;
use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SubscriptionService
{
    use ApiResponse, TransactionWrapper;

    /**
     * Get the list of trail resources for a specified user or default.
     *
     * @param int|null $userId Optional user ID to fetch specific resources.
     *
     * @return array List of trail resources.
     */
    public function trailResourceList(?int $userId = null): ?array
    {
        return $this->runInTransaction(function () use ($userId) {
            $resources = $this->getResourcesForUser($userId);

            $encryptedResources = $resources->map(function ($resource) {
                return $this->formatResource($resource);
            });

            return $this->successResponse($encryptedResources, 'Resource listing');
        });
    }
    /**
     * Update trail data and return result as array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function updateTrailData(Request $request): ?array
    {
        return $this->runInTransaction(function () use ($request) {
            $userId = $this->getUserIdFromToken($request);
            $resourceId = $this->decryptedValues($request->resource_id);
            $this->buildTrailHistory($userId, $resourceId);
            $this->updateUserTrailOption($userId);

            return $this->successResponse(null, 'Resource for Trail');
        });
    }
    /**
     * Get the list of all user subscriptions.
     *
     * @return array List of subscription records.
     */
    public function getSubscriptionList(): array
    {
        return $this->runInTransaction(function () {
            $resources = Resources::select('id', 'resource_name', 'annual_fee')->get();
            $encryptedResources = $resources->map(function ($resource) {
                return $this->formatResource($resource);
            });

            return $this->successResponse($encryptedResources, 'Resource listing');
        });
    }
    /**
     * Build or log the trail history for a given user and resource.
     *
     * @param int $userId The ID of the user.
     * @param int $resourceId The ID of the resource.
     *
     * @return void
     */
    public function buildTrailHistory(int $userId, int $resourceId): void
    {
        TrailHistory::create([
            'user_id' => $userId,
            'resource_id' => $resourceId,
            'trail_start_date' => now(),
            'trail_end_date' => now()->addDays(7),
            'trail_expired_at' => now()->addDays(7),
        ]);
    }
    /**
     * Build or log the trail history for a given user and resource.
     *
     * @param int $userId The ID of the user.
     * @param int $resourceId The ID of the resource.
     *
     * @return void
     */
    public function buildSubscriptionHistory(int $typeId, int $resourceId): void
    {
        $resource = Resources::where('id', $resourceId)->first();
        $startDate = now();
        $endDate = $startDate->copy()->addYear();
        SubscriptionHistory::create([
            'type_id' => $typeId,
            'subscription_amount' => $resource->annual_fee,
            'subscription_start_date' => $startDate,
            'subscription_end_date' => $endDate,
            'fee_type' => SubscriptionHistory::ANNUAL_FEE,
        ]);
    }
    /**
     * Format a single resource for API response, including encryption and display logic.
     *
     * @param object $resource The resource object containing id, name, and annual fee.
     *
     * @return array An array with encrypted resource ID and formatted display fields.
     */
    private function formatResource($resource): array
    {
        $details = $this->getFormattedResourceDetails($resource);

        return [
            'resourceId' => Crypt::encrypt($resource->id),
            'resourceName' => $details['resourceName'],
            'amount' => $details['resourceAmount'],
            'amountText' => $details['amountText'],
            'subText' => $details['subText'],
        ];
    }
    /**
     * Format resource details based on whether the resource is a trial or not.
     *
     * @param object $resource The resource object containing name and annual fee.
     *
     * @return array An array with formatted resourceName, amount, amountText, and subText.
     */
    private function getFormattedResourceDetails($resource): array
    {
        $isTrial = strtolower($resource->resource_name) === 'trail';

        return [
            'resourceName' => $isTrial ? 'Free 7 Day Trial' : $resource->resource_name,
            'resourceAmount' => $isTrial ? 'Free' : '£' . $resource->annual_fee,
            'amountText' => $isTrial ? null : 'Per anum',
            'subText' => $isTrial ? 'Allows you to test out the system.' : null,
        ];
    }
    /**
     * Update trail-related options or preferences for the given user.
     *
     * @param int $userId The ID of the user.
     *
     * @return void
     */
    private function updateUserTrailOption(int $userId): void
    {
        User::where('id', $userId)->update([
            'is_trail' => 1,
        ]);
    }
    /**
     * Retrieve resources accessible by the specified user.
     *
     * @param int|null $userId
     *
     * @return object Object containing user resources.
     */
    private function getResourcesForUser(?int $userId): object
    {
        if ($userId !== null) {
            $resourceIds = UserSubscription::where('user_id', $userId)
                ->pluck('resource_id');

            return Resources::whereIn('id', $resourceIds)
                ->select('id', 'resource_name')
                ->get();
        }

        return Resources::select('id', 'resource_name', 'annual_fee')
            ->where('type', '!=', TrailHistory::TRAIL)
            ->get();
    }
}
