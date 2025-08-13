<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'App\Api\Parent\Modules\Signup\Controllers'], function () {
    Route::post('/parent/signup', 'ParentLoginController@parentSignupOrLogin')->name('parent.signup');
    Route::post('/parent/forgotpassword', 'ParentLoginController@parentForgotPassword')->name('parent.forgotpassword');
    Route::post('/parent/logout', 'ParentLoginController@parentLogout')->name('parent.signout');
});
