<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Dashboard\Controllers;

use App\Api\Admin\Modules\Resources\Models\Resources;
use App\Http\Controllers\Api\BaseController;
use App\Models\ModelHasRoles;
use App\Models\Modules;
use App\Models\User;
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
     * @return \Illuminate\Http\JsonResponse The JSON response containing dashboard statistics.
     */
    public function adminDashboard()
    {
        return $this->runInTransaction(function () {
            $users = $this->getUserStatistics();
            $resources = $this->getResourceStatistics();
            $data = [
                'users' => $users,
                'resources' => $resources,
                'sales' => [
                    'totalCount' => '£0.00',
                    'lastMonthCount' => '£0.00',
                ],
            ];

            return $this->sendResponse($data, 'Admin dashboard data fetched successfully');
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
        $totalResources = Resources::count();
        $newResourcesLastMonth = Resources::whereBetween('created_at', [$lastMonth, $now])
            ->count();

        return [
            'totalCount' => $totalResources,
            'lastMonthCount' => $newResourcesLastMonth,
        ];
    }
}
