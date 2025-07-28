<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Playlist\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\PlaylistService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaylistController extends BaseController
{
    use ApiResponse;

    /**
     * @var PlaylistService
     */
    protected $playlistService;

    public function __construct(PlaylistService $playlistService)
    {
        $this->playlistService = $playlistService;
    }

    /**
     * Handle create playlist.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminCreatePlaylist(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->playlistService->createPlaylist($request)
        );
    }
    /**
     * Handle view playlist.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminViewPlaylist(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->playlistService->viewPlaylist($request)
        );
    }
    /**
     * Handle view particular playlist.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewPlaylistInfo(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->playlistService->getPlaylistInfo($request)
        );
    }
    /**
     * Handle delete playlist.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminDeletePlaylist(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->playlistService->deletePlaylist($request)
        );
    }
    /**
     * Handle view particular playlist.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminPlaylistQr(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->playlistService->generatePlaylistQr($request)
        );
    }
    /**
     * Handle edit playlist.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminEditPlaylist(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->playlistService->editPlaylist($request)
        );
    }
}
