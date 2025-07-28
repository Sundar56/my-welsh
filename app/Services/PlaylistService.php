<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Resources\Models\ModuleResourceTopic;
use App\Api\Teacher\Modules\Playlists\Models\Playlist;
use App\Api\Teacher\Modules\Playlists\Models\PlaylistResource;
use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PlaylistService
{
    use ApiResponse, TransactionWrapper;

    /**
     * Handle to create playlist based on resources.
     *
     * @param Request $request
     *
     * @return array
     */
    public function createPlaylist(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $validationErrors = $this->validatePlaylist($request);
            if ($validationErrors) {
                return $this->validationErrorResponse($validationErrors);
            }
            $playlist = $this->addPlaylist($request);
            $topicsWithPosition = $this->getTopicIds($request->topic_ids, $request->position);
            $this->addTopicsToPlaylist($playlist['id'], $topicsWithPosition);

            return $this->successResponse(null, 'Playlist created successfully');
        });
    }
    /**
     * Handle to view playlist based on resources.
     *
     * @param Request $request
     *
     * @return array
     */
    public function viewPlaylist(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $playlistInfo = $this->playlistInfo($request);
            $data = $playlistInfo['playlistData'];
            return $this->successResponse($data, 'Playlist Data');
        });
    }
    /**
     * Create a new playlist.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function addPlaylist($request): ?array
    {
        $userId = $this->getUserIdFromToken($request);
        $resourceId = $this->decryptedValues($request->resource_id);
        $playlist = Playlist::create([
            'user_id' => $userId,
            'resource_id' => $resourceId,
            'playlist_name' => $request->playlist_name,
            'is_shared' => $request->is_shared,
        ]);

        return [
            'id' => $playlist->id,
        ];
    }
    /**
     * Create a new playlist.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function playlistInfo($request): ?array
    {
        $userId = $this->getUserIdFromToken($request);
        $playlists = Playlist::where('user_id', $userId)
            ->get(['id', 'playlist_name', 'resource_id']);

        $result = [];

        foreach ($playlists as $playlist) {
            $topicDetails = $this->getTopicDetailsByPlaylistId($playlist->id);
            $result[] = [
                'playlist_id' => $this->encryptedValues($playlist->id),
                'resourceId' => $this->encryptedValues($playlist->resource_id),
                'playlist_name' => $playlist->playlist_name,
                'topics' => $topicDetails,
            ];
        }

        return [
            'playlistData' => $result,
        ];
    }
    /**
     * Handle to view playlist based on particular playlist.
     *
     * @param Request $request
     *
     * @return array
     */
    public function getPlaylistInfo(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $playlistId = $this->decryptedValues($request->playlist_id);
            $playlist = Playlist::where('id', $playlistId)->select('playlist_name')->first();
            if (! $playlist) {
                return $this->failedResponse('Playlist not found');
            }
            $topicDetails = $this->getTopicDetailsByPlaylistId($playlistId);

            $result = [
                'playlist_name' => $playlist->playlist_name,
                'topics' => $topicDetails,
            ];

            return $this->successResponse($result, 'Playlist Data');
        });
    }
    /**
     * Delete the specified playlist based on the incoming request data.
     *
     * @param \Illuminate\Http\Request $request  The HTTP request containing the playlist ID or related data.
     *
     * @return array
     */
    public function deletePlaylist(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $playlist = $this->getValidPlaylist($request);

            if (! $playlist) {
                return $this->failedResponse('Playlist not found');
            }
            $playlist->delete();

            return $this->successResponse(null, 'Playlist deleted successfully');
        });
    }
    /**
     * Generate a QR code and shareable URL for the given playlist ID.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function generatePlaylistQr(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $playlist = $this->getValidPlaylist($request);
            if (! $playlist) {
                return $this->failedResponse('Playlist not found');
            }

            $data = $this->buildQrCodeForPlaylist($request->playlist_id);
            return $this->successResponse($data, 'Share playlist');
        });
    }
    /**
     * Handle to create playlist based on resources.
     *
     * @param Request $request
     *
     * @return array
     */
    public function editPlaylist(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $validationErrors = $this->validatePlaylist($request);
            if ($validationErrors) {
                return $this->validationErrorResponse($validationErrors);
            }
            $playlistId = $this->decryptedValues($request->playlist_id);
            $this->updatePlaylist($request, $playlistId);
            $topicsWithPosition = $this->getTopicIds($request->topic_ids, $request->position);
            $this->deleteTopicsFromPlaylist($playlistId);
            $this->addTopicsToPlaylist($playlistId, $topicsWithPosition);

            return $this->successResponse(null, 'Playlist updated successfully');
        });
    }
    /**
     * Build QR code and URL data for a given playlist ID.
     *
     * @param string $encryptedPlaylistId  The encrypted playlist ID used for URL generation.
     *
     * @return array
     */
    protected function buildQrCodeForPlaylist(string $encryptedPlaylistId): array
    {
        $baseUrl = env('FRONT_END_URL');
        $url = $baseUrl . '/playlists/' . $encryptedPlaylistId;
        $qrCode = QrCode::format('svg')->size(200)->generate($url);
        $base64Qr = 'data:image/svg+xml;base64,' . base64_encode((string) $qrCode);

        return [
            'playlist_id' => $encryptedPlaylistId,
            'url' => $url,
            'qr_code' => $base64Qr,
        ];
    }
    protected function getValidPlaylist(Request $request): ?Playlist
    {
        try {
            $playlistId = $this->decryptedValues($request->playlist_id);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return null;
        }

        return Playlist::find($playlistId);
    }
    /**
     * Add multiple topics to the given playlist.
     *
     * @param int $playlistId  The ID of the playlist.
     * @param array $topicIds  An array of topic IDs to associate with the playlist.
     *
     * @return void
     */
    private function addTopicsToPlaylist(int $playlistId, array $topicsWithPosition): void
    {
        foreach ($topicsWithPosition as $item) {
            PlaylistResource::create([
                'playlist_id' => $playlistId,
                'module_resource_topic_id' => $item['topic_id'],
                'position' => $item['position'],
            ]);
        }
    }

    /**
     * Ensure topic IDs are returned as an array.
     *
     * @param array|string|null $input
     *
     * @return array
     */
    private function getTopicIds(array $topicIds, array $positions): array
    {
        $result = [];

        foreach ($topicIds as $index => $encryptedId) {
            if (! isset($positions[$index])) {
                continue;
            }

            try {
                $decryptedId = $this->decryptedValues($encryptedId);
            } catch (\Exception $e) {
                continue;
            }

            $result[] = [
                'topic_id' => $decryptedId,
                'position' => (int) $positions[$index],
            ];
        }

        return $result;
    }
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validatePlaylist(Request $request): ?array
    {
        $rules = [
            'playlist_name' => 'required',
        ];
        $messages = [
            'playlist_name.required' => 'Playlist Name is required',
        ];

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * Get topic details by playlist ID, including position from PlaylistResource.
     *
     * @param int $playlistId The ID of the playlist to fetch topics for.
     *
     * @return array An array of topic data merged with their corresponding position.
     */
    private function getTopicDetailsByPlaylistId(int $playlistId): array
    {
        $playlistResources = $this->getPlaylistResourcesWithPosition($playlistId);
        $topicIds = $playlistResources->pluck('module_resource_topic_id')->toArray();
        $topics = ModuleResourceTopic::whereIn('id', $topicIds)->get()->keyBy('id');
        $result = [];

        foreach ($playlistResources as $resource) {
            $topic = $topics->get($resource->module_resource_topic_id);
            if ($topic) {
                $topicArray = $topic->toArray();
                $topicArray['id'] = $this->encryptedValues($topic->id);
                $result[] = array_merge(
                    $topicArray,
                    ['position' => $resource->position]
                );
            }
        }
        return $result;
    }
    /**
     * Get playlist resources with topic ID and position.
     *
     * @param int $playlistId The ID of the playlist.
     *
     * @return \Illuminate\Support\Collection
     */
    private function getPlaylistResourcesWithPosition(int $playlistId)
    {
        return PlaylistResource::where('playlist_id', $playlistId)
            ->get(['module_resource_topic_id', 'position']);
    }
    /**
     * Updates the name of a playlist and reassigns topics with their positions.
     *
     * @param Request $request  The incoming request containing playlist ID, name, topic IDs, and positions.
     * @param int $playlistId  The ID of the playlist.
     *
     * @return void
     */
    private function updatePlaylist(Request $request, int $playlistId): void
    {
        Playlist::where('id', $playlistId)->update([
            'playlist_name' => $request->playlist_name,
            'is_shared' => $request->is_shared,
        ]);
    }
    /**
     * Delete multiple topics to the given playlist.
     *
     * @param int $playlistId  The ID of the playlist.
     *
     * @return void
     */
    private function deleteTopicsFromPlaylist(int $playlistId): void
    {
        PlaylistResource::where('playlist_id', $playlistId)->delete();
    }
}
