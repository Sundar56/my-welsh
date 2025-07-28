<?php

declare(strict_types=1);

namespace App\Api\Parent\Modules\Playlists\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\PlaylistService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ViewPlaylistController extends BaseController
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
     * Handle view playlist.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewPlaylistByParent(Request $request): JsonResponse
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
    public function parentPlaylistInfo(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->playlistService->getPlaylistInfo($request)
        );
    }
}
