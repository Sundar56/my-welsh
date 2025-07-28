<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Admin\Modules\Dashboard\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::post('/admin/modules', 'DashboardController@modulesList')->name('admin.modules.get');
    Route::get('/admin/dashboard', 'DashboardController@adminDashboard')->name('admin.dashboard.get');
});
