<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Teacher\Modules\Playlists\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::post('/createplaylist', 'PlaylistController@createPlaylist')->name('teacher.playlist.create');
    Route::get('/viewplaylist', 'PlaylistController@viewPlaylist')->name('teacher.playlist.get');
    Route::post('/playlistinfo', 'PlaylistController@viewPlaylistInfo')->name('teacher.playlist.view');
    Route::post('/deleteplaylist', 'PlaylistController@deletePlaylist')->name('teacher.playlist.delete');
    Route::post('/generateqrcode', 'PlaylistController@generateQr')->name('teacher.playlist.qrcode');
    Route::post('/editplaylist', 'PlaylistController@editPlaylist')->name('teacher.playlist.edit');
});
