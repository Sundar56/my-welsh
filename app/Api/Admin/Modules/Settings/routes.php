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

    Route::post('/admin/createsettings', 'AdminSettingController@createSettings')->name('admin.settings.createsettings');
    Route::post('/admin/viewsettings', 'AdminSettingController@viewAdminSettings')->name('admin.settings.viewsettings');
    Route::post('/admin/editsettings', 'AdminSettingController@editAdminSettings')->name('admin.settings.editsettings');
});
