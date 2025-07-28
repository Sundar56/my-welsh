<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'App\Api\Admin\Modules\Adminlogin\Controllers'], function () {
    Route::post('/admin/login', 'AdminloginController@adminLogin')->name('admin.login');
    Route::post('/admin/forgotpassword', 'AdminloginController@adminForgotPassword')->name('admin.forgotpassword');
});

Route::group([
    'namespace' => 'App\Api\Admin\Modules\Adminlogin\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::post('/admin/signout', 'AdminloginController@adminLogout')->name('admin.logout');
});
