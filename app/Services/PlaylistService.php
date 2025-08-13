<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Resources\Models\ModuleResources;
use App\Api\Admin\Modules\Resources\Models\ModuleResourceTopic;
use App\Api\Parent\Modules\Signup\Models\ParentPlaylists;
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

            $lang = $request->language ?? 'en';
            return $this->successResponse(null, trans('message.success.playlist_created', [], $lang));
        });
    }
    /**
     * Handle to view playlist based on resources.
     *
     * @param Request $request
     * @param string $type
     *
     * @return array
     */
    public function viewPlaylist(Request $request, string $type = 'user'): array
    {
        return $this->runInTransaction(function () use ($request, $type) {
            $playlistInfo = $this->playlistInfo($request, $type);

            $data = $playlistInfo['playlistData'];
            $lang = $request->query('language', 'en');

            return $this->successResponse($data, trans('message.success.playlist_data', [], $lang));
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
     * View playlist.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $type
     *
     * @return array
     */
    public function playlistInfo(Request $request, string $type = 'user'): ?array
    {
        $userId = $this->getUserIdFromToken($request);
        $playlists = match ($type) {
            'parent' => $this->getParentPlaylists($userId),
            default => $this->getUserPlaylists($userId),
        };
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
                $lang = $request->language_code ?? 'en';
                return $this->failedResponse(trans('message.errors.playlist_not_found', [], $lang));
            }
            $topicDetails = $this->getTopicDetailsByPlaylistId($playlistId);

            $result = [
                'playlist_name' => $playlist->playlist_name,
                'topics' => $topicDetails,
            ];
            $lang = $request->language ?? 'en';

            return $this->successResponse($result, trans('message.success.playlist_data', [], $lang));
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
                $lang = $request->language_code ?? 'en';
                return $this->failedResponse(trans('message.errors.playlist_not_found', [], $lang));
            }
            $playlist->delete();

            $lang = $request->language ?? 'en';

            return $this->successResponse(null, trans('message.success.playlist_deleted', [], $lang));
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
                $lang = $request->language_code ?? 'en';
                return $this->failedResponse(trans('message.errors.playlist_not_found', [], $lang));
            }
            $userId = $this->getUserIdFromToken($request);
            $data = $this->buildQrCodeForPlaylist($request->playlist_id, $userId);
            $lang = $request->language ?? 'en';

            return $this->successResponse($data, trans('message.success.playlist_shared', [], $lang));
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
            $lang = $request->language ?? 'en';

            return $this->successResponse(null, trans('message.success.playlist_updated', [], $lang));
        });
    }
    /**
     * Build QR code and URL data for a given playlist ID.
     *
     * @param string $encryptedPlaylistId  The encrypted playlist ID used for URL generation.
     * @param int $userId  The user ID used for URL generation.
     *
     * @return array
     */
    protected function buildQrCodeForPlaylist(string $encryptedPlaylistId, int $userId): array
    {
        $baseUrl = env('FRONT_END_URL');
        $parentUrl = env('PARENT_LOGIN_URL');
        $encryptedUserId = $this->encryptedValues($userId);
        $expiryTimestamp = now()->addDays(30)->timestamp;
        $encryptedExpiry = $this->encryptedValues($expiryTimestamp);
        $url = "{$baseUrl}/{$parentUrl}/";
        $playlistIdUrl = $url . '?id=' . $encryptedUserId . '&playlistId=' . $encryptedPlaylistId . '&exp=' . $encryptedExpiry;
        $qrCode = QrCode::format('svg')->size(200)->generate($playlistIdUrl);
        $base64Qr = 'data:image/svg+xml;base64,' . base64_encode((string) $qrCode);

        return [
            'playlist_id' => $encryptedPlaylistId,
            'url' => $playlistIdUrl,
            'qr_code' => $base64Qr,
        ];
    }
    /**
     * Retrieves and validates a playlist from the given request.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request containing playlist identification data.
     *
     * @return \App\Models\Playlist|null The valid Playlist instance or null if not found or invalid.
     */
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
     * Get playlists for a regular user.
     *
     * @param int $userId
     *
     * @return \Illuminate\Support\Collection
     */
    private function getUserPlaylists(int $userId)
    {
        return Playlist::where('user_id', $userId)
            ->get(['id', 'playlist_name', 'resource_id']);
    }

    /**
     * Get playlists for a parent user.
     *
     * @param int $userId
     *
     * @return \Illuminate\Support\Collection
     */
    private function getParentPlaylists(int $userId)
    {
        $parentPlaylists = ParentPlaylists::where('parent_id', $userId)
            ->pluck('playlist_id');

        return Playlist::whereIn('id', $parentPlaylists)
            ->get(['id', 'playlist_name', 'resource_id']);
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
        $lang = $request->language ?? 'en';

        $rules = [
            'playlist_name' => 'required',
        ];
        $messages = trans('message.errors', [], $lang);

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
        return $this->formatTopicsWithModules($playlistResources);
    }
    /**
     * Formats playlist resources by retrieving associated topics and their module names.
     *
     * @param \Illuminate\Support\Collection $playlistResources  The playlist resources with positions.
     *
     * @return array  Formatted list of topic details with module names and positions.
     */
    private function formatTopicsWithModules($playlistResources): array
    {
        $topicIds = $playlistResources->pluck('module_resource_topic_id')->toArray();
        $topics = ModuleResourceTopic::whereIn('id', $topicIds)->get()->keyBy('id');

        $moduleResourceIds = $topics->pluck('module_resource_id')->unique()->toArray();
        $modules = ModuleResources::whereIn('id', $moduleResourceIds)
            ->pluck('module_name', 'id');

        return $this->mapPlaylistResourcesToTopics($playlistResources, $topics, $modules);
    }
    /**
     * Maps playlist resources to their corresponding topic data.
     *
     * @param \Illuminate\Support\Collection $playlistResources  The playlist resources with positions.
     * @param \Illuminate\Support\Collection $topics             The topics keyed by ID.
     * @param \Illuminate\Support\Collection $modules            Module names keyed by module resource ID.
     *
     * @return array  Mapped list of topic data including module names and positions.
     */
    private function mapPlaylistResourcesToTopics($playlistResources, $topics, $modules): array
    {
        $result = [];

        foreach ($playlistResources as $resource) {
            $topic = $topics->get($resource->module_resource_topic_id);

            if ($topic) {
                $result[] = $this->buildTopicData($topic, $resource->position, $modules);
            }
        }

        return $result;
    }
    /**
     * Builds a formatted topic array with encrypted ID, module name, and position.
     *
     * @param \App\Models\ModuleResourceTopic $topic  The topic model instance.
     * @param int $position                           The position of the topic in the playlist.
     * @param \Illuminate\Support\Collection $modules Module names keyed by module resource ID.
     *
     * @return array  Formatted topic data.
     */
    private function buildTopicData($topic, int $position, $modules): array
    {
        $topicArray = $topic->toArray();
        $topicArray['id'] = $this->encryptedValues($topic->id);
        $topicArray['module_name'] = $modules[$topic->module_resource_id] ?? null;

        return array_merge($topicArray, ['position' => $position]);
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
