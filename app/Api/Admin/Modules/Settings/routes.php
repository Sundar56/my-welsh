<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Admin\Modules\Settings\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::get('/admin/viewprofile', 'AdminSettingController@adminViewProfile')->name('admin.settings.view');
    Route::post('/admin/editprofile', 'AdminSettingController@adminEditProfile')->name('admin.settings.edit');
});
