<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invoice', function () {
    return view('emails.invioce');
});

Route::get('/logo-path', function () {
    $logo = env('FFALALA_LOGO');
    $appUrl = env('APP_URL');

    return $appUrl . '/' . $logo;
});
