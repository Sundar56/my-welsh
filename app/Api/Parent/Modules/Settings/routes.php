<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Parent\Modules\Settings\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::get('/parent/viewsetting', 'ParentSettingsController@viewProfileByParent')->name('parent.settings.view');
    Route::post('/parent/editsetting', 'ParentSettingsController@editProfileByParent')->name('parent.settings.edit');
});
