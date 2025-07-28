<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Parent\Modules\Playlists\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::get('/parent/viewplaylist', 'ViewPlaylistController@viewPlaylistByParent')->name('parent.playlist.get');
    Route::post('/parent/playlistinfo', 'ViewPlaylistController@parentPlaylistInfo')->name('parent.playlist.view');
});
