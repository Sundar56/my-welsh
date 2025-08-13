<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

$proxy_enabled = getenv('PROXY_ENABLED');
if (isset($proxy_enabled) && $proxy_enabled === true) {
    $proxy_url = getenv('PROXY_URL');
    $proxy_schema = getenv('PROXY_SCHEMA');

    if (isset($proxy_url) && $proxy_url !== '') {
        URL::forceRootUrl($proxy_url);
    }

    if (isset($proxy_schema) && $proxy_schema !== '') {
        URL::forceScheme($proxy_schema);
    }
}

Route::group(['namespace' => 'App\Http\Controllers\Api'], function (): void {
    Route::post('/login', 'LoginController@login');
    Route::post('/forgotpassword', 'LoginController@forgotPassword')
        ->name('forgotpassword');

    Route::get('/languages', 'LoginController@getLanguages');
});

Route::group(['namespace' => 'App\Http\Controllers\Api'], function (): void {
    Route::post('/getlanguages', 'LanguageController@getLanguages');
});

Route::group([
    'namespace' => 'App\Http\Controllers\Api',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function (): void {
    Route::post('/signout', 'LoginController@logout')->name('signout');
});
