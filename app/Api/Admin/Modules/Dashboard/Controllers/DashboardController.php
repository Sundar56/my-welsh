<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Dashboard\Controllers;

use App\Api\Admin\Modules\Resources\Models\Resources;
use App\Http\Controllers\Api\BaseController;
use App\Models\ModelHasRoles;
use App\Models\Modules;
use App\Models\TrailHistory;
use App\Models\User;
use App\Models\UserSubscription;
use App\Traits\TransactionWrapper;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    use TransactionWrapper;

    /**
     * Retrieve the list of modules for the admin panel.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request instance.
     *
     * @return \Illuminate\Http\JsonResponse The JSON response containing the modules list.
     */
    public function modulesList(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $modules = Modules::where('type', $request->module_type)->get();
            if (! $modules) {
                return $this->sendError('No modules found', ['error' => 'No modules found'], 404);
            }

            return $this->sendResponse($modules, 'Admin modules list');
        });
    }
    /**
     * Display the admin dashboard data.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request instance.
     *
     * @return \Illuminate\Http\JsonResponse The JSON response containing dashboard statistics.
     */
    public function adminDashboard(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $users = $this->getUserStatistics();
            $resources = $this->getResourceStatistics();
            $sales = $this->getSalesStatistics();
            $data = [
                'users' => $users,
                'resources' => $resources,
                'sales' => $sales,
            ];
            $lang = $request->query('language', 'en');
            return $this->sendResponse($data, trans('message.success.dashboard_success', [], $lang));
        });
    }
    /**
     * Get user statistics including total users and users added in the last month.
     *
     * @return array
     */
    private function getUserStatistics(): array
    {
        $now = now();
        $lastMonth = $now->copy()->subMonth();
        $totalUsers = User::where('id', '!=', ModelHasRoles::ADMIN)->count();
        $newUsersLastMonth = User::where('id', '!=', ModelHasRoles::ADMIN)
            ->whereBetween('created_at', [$lastMonth, $now])
            ->count();

        return [
            'totalCount' => $totalUsers,
            'lastMonthCount' => $newUsersLastMonth,
        ];
    }
    /**
     * Get Resource statistics including total resources and resources added in the last month.
     *
     * @return array
     */
    private function getResourceStatistics(): array
    {
        $now = now();
        $lastMonth = $now->copy()->subMonth();
        $totalResources = Resources::where('type', '!=', TrailHistory::TRAIL)->count();
        $newResourcesLastMonth = Resources::where('type', '!=', TrailHistory::TRAIL)->whereBetween('created_at', [$lastMonth, $now])
            ->count();

        return [
            'totalCount' => $totalResources,
            'lastMonthCount' => $newResourcesLastMonth,
        ];
    }
    /**
     * Calculate subscription sales statistics.
     *
     * Retrieves the total subscription sales amount and the sales amount
     * generated within the last month from active and latest subscriptions.
     *
     * @return array
     */
    private function getSalesStatistics(): array
    {
        $now = now();
        $lastMonth = $now->copy()->subMonth();
        $query = UserSubscription::leftJoin('subscription_history', 'user_subscription.id', '=', 'subscription_history.type_id')
            ->where('user_subscription.latest_subscription', UserSubscription::STATUS_ONE)
            ->where('user_subscription.status', UserSubscription::STATUS_ONE);

        $totalSalesAmount = $query->sum('subscription_history.subscription_amount');
        $newSalesThisMonth = $query
            ->where('subscription_history.created_at', '>=', $lastMonth)
            ->sum('subscription_history.subscription_amount');

        return [
            'totalCount' => '£' . number_format($totalSalesAmount, 2),
            'lastMonthCount' => '£' . number_format($newSalesThisMonth, 2),
        ];
    }
}
