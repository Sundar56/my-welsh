<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Resources\Models\Resources;
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
                return $this->formatResource($resource, 'en');
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
            $lang = $request->language_code ?? 'en';

            return $this->successResponse(null, trans('message.success.resource_trail', [], $lang));
        });
    }
    /**
     * Get the list of all user subscriptions.
     *
     * @return array List of subscription records.
     */
    public function getSubscriptionList(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $resources = Resources::select('id', 'resource_name', 'annual_fee')->get();
            $encryptedResources = $resources->map(function ($resource) use ($request) {
                // $lang = $request->query('language', 'en');
                $lang = $request->route('lang', 'en');
                return $this->formatResource($resource, $lang);
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
     * Format a single resource for API response, including encryption and display logic.
     *
     * @param object $resource The resource object containing id, name, and annual fee.
     * @param string|null $lang The lang of the user.
     *
     * @return array An array with encrypted resource ID and formatted display fields.
     */
    private function formatResource($resource, ?string $lang): array
    {
        $details = $this->getFormattedResourceDetails($resource, $lang);

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
     * @param string|null $lang The lang of the user.
     *
     * @return array An array with formatted resourceName, amount, amountText, and subText.
     */
    private function getFormattedResourceDetails($resource, ?string $lang): array
    {
        $isTrial = strtolower($resource->resource_name) === 'trail';

        if ($isTrial && $lang) {
            return $this->getTrialResourceInfo($lang);
        }
        return $this->getRegularResourceInfo($resource, $lang);
    }
    /**
     * Get resource info for trial users.
     *
     * @param string|null $lang The lang of the user.
     *
     * @return array
     */
    private function getTrialResourceInfo(?string $lang): array
    {
        return [
            'resourceName' => trans('message.subscriptions.trial_title', [], $lang),
            'resourceAmount' => trans('message.subscriptions.free', [], $lang),
            'amountText' => null,
            'subText' => trans('message.subscriptions.trial_description', [], $lang),
        ];
    }
    /**
     * Get resource info for regular (non-trial) users.
     *
     * @param string|null $lang The lang of the user.
     * @param object $resource Object containing resource_name and annual_fee properties.
     *
     * @return array
     */
    private function getRegularResourceInfo($resource, ?string $lang): array
    {
        return [
            'resourceName' => $resource->resource_name,
            'resourceAmount' => '£' . $resource->annual_fee,
            'amountText' => trans('message.subscriptions.text_annum', [], $lang),
            'subText' => null,
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
            $user = User::where('id', $userId)->select('is_trail')->first();

            return $this->getUserResourcesByType($userId, $user->is_trail === UserSubscription::STATUS_ONE);
        }

        return Resources::select('id', 'resource_name', 'annual_fee')
            ->where('type', '!=', TrailHistory::TRAIL)
            ->get();
    }
    /**
     * Fetch resources for a user based on whether they are on a trial or a paid subscription.
     *
     * @param int $userId The ID of the user.
     * @param bool $isTrial Indicates if the user is on a trial.
     *
     * @return object A collection of resources (id and resource_name) assigned to the user.
     */
    private function getUserResourcesByType(int $userId, bool $isTrial): object
    {
        $resourceIds = $isTrial
            ? TrailHistory::where('user_id', $userId)->pluck('resource_id')
            : UserSubscription::where('user_id', $userId)
                ->where('latest_subscription', UserSubscription::STATUS_ONE)
                ->pluck('resource_id');

        return Resources::whereIn('id', $resourceIds)
            ->select('id', 'resource_name')
            ->get();
    }
}
