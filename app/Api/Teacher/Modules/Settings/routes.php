<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Teacher\Modules\Settings\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::get('/viewprofile', 'SettingsController@viewProfile')->name('teacher.settings.view');
    Route::post('/editprofile', 'SettingsController@editProfile')->name('teacher.settings.edit');
    Route::post('/cancelsubscription', 'SettingsController@cancelSubscription')->name('teacher.settings.cancel');
});
