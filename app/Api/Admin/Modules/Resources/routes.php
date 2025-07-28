<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Admin\Modules\Resources\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::post('/admin/addresource', 'ResourceController@addResource')->name('admin.resources.create');
    Route::post('/admin/viewresource', 'ResourceController@resourceInfo')->name('admin.resources.view');
    Route::get('/admin/getresources', 'ResourceController@getResources')->name('admin.resources.get');
    Route::post('/admin/editresource', 'ResourceController@editResource')->name('admin.resources.edit');
    Route::post('/admin/deletetopic', 'ResourceController@adminDeleteTopic')->name('admin.resources.delete');

    Route::get('/admin/getmodulestopic', 'ResourceController@alModulesWithTopics')->name('admin.resources.gettopic');
});
