<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Admin\Modules\Playlist\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::post('/admin/createplaylist', 'PlaylistController@adminCreatePlaylist')->name('admin.playlist.create');
    Route::get('/admin/viewplaylist', 'PlaylistController@adminViewPlaylist')->name('admin.playlist.get');
    Route::post('/admin/playlistinfo', 'PlaylistController@viewPlaylistInfo')->name('admin.playlist.view');
    Route::post('/admin/deleteplaylist', 'PlaylistController@adminDeletePlaylist')->name('admin.playlist.delete');
    Route::post('/admin/playlistqrcode', 'PlaylistController@adminPlaylistQr')->name('admin.playlist.qrcode');
    Route::post('/admin/editplaylist', 'PlaylistController@adminEditPlaylist')->name('admin.playlist.edit');
});
