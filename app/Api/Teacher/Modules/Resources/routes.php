<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Teacher\Modules\Resources\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::post('/viewresource', 'ResourceController@viewResourceInfo')->name('teacher.resource.view');
    Route::get('/resourceslist', 'ResourceController@resourceList')->name('teacher.resource.get');
    Route::get('/modulestopiclist', 'ResourceController@allModulesList')->name('teacher.resource.gettopics');
});
