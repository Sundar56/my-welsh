<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Resources\Models\ModuleResources;
use App\Api\Admin\Modules\Resources\Models\ModuleResourceTopic;
use App\Api\Admin\Modules\Resources\Models\Resources;
use App\Models\ModelHasRoles;
use App\Models\UserSubscription;
use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Illuminate\Http\Request;

class ResourceService
{
    use ApiResponse, TransactionWrapper;

    protected DataSecurityService $dataSecurityService;
    protected UploadFileService $uploadFileService;

    public function __construct(DataSecurityService $dataSecurityService, UploadFileService $uploadFileService)
    {
        $this->dataSecurityService = $dataSecurityService;
        $this->uploadFileService = $uploadFileService;
    }
    /**
     * Handle add resource with module name.
     *
     * @param Request $request
     *
     * @return array
     */
    public function addResource(Request $request): ?array
    {
        return $this->runInTransaction(function () use ($request) {
            $validationErrors = $this->validateResource($request);
            if ($validationErrors) {
                return $this->validationErrorResponse($validationErrors);
            }
            $resource = $this->createResource($request);
            $uploadError = $this->handleModulesAndTopics($request->modules, $resource['resourceId']);
            if ($uploadError) {
                return $uploadError;
            }

            return $this->successResponse(null, 'Resource with modules and topics created successfully');
        });
    }
    /**
     * Create a new resource and its associated module resource.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function createResource($request): ?array
    {
        $resource = Resources::create([
            'resource_name' => $request->resource_name,
            'monthly_fee' => $request->monthly_amount,
            'annual_fee' => $request->annual_amount,
        ]);

        return [
            'resourceId' => $resource->id,
        ];
    }
    /**
     * Handle view resource with module name.
     *
     * @param Request $request
     *
     * @return array
     */
    public function resourceInfo(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $resourceId = $this->decryptedValues($request->resource_id);
            $resource = Resources::where('id', $resourceId)->first();
            $modulesWithTopics = $this->formatSingleResource($resourceId);

            $resourceInfo = [
                'resourceName' => $resource->resource_name,
                'monthlyAmount' => $resource->monthly_fee,
                'annualAmount' => $resource->annual_fee,
            ];
            $data = array_merge($resourceInfo, $modulesWithTopics);

            return $this->successResponse($data, 'Resource details');
        });
    }
    /**
     * Create a new resource topic for the specified module.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request containing topic data.
     * @param int $moduleId The ID of the module to associate the resource topic with.
     *
     * @return array|null Returns an array containing the created topic ID, or null on failure.
     */
    public function createResourceTopic(Request $request, int $moduleId): ?array
    {
        $videoUrl = $request->resource_type === '0' ? $request->video_url : null;

        $resourceTopic = ModuleResourceTopic::create([
            'module_resource_id' => $moduleId,
            'resource_topic' => $request->topic_name,
            'resource_type' => $request->resource_type,
            'description' => $request->description,
            'video_url' => $videoUrl,
        ]);

        return [
            'topicId' => $resourceTopic->id,
        ];
    }
    /**
     * Handle edit resource with module name.
     *
     * @param Request $request
     *
     * @return array
     */
    public function editResource(Request $request): ?array
    {
        return $this->runInTransaction(function () use ($request) {
            $validationErrors = $this->validateResource($request);
            if ($validationErrors) {
                return $this->validationErrorResponse($validationErrors);
            }
            $this->updateResource($request);
            $uploadError = $this->updateModulesAndTopics($request->modules);
            if ($uploadError) {
                return $uploadError;
            }

            return $this->successResponse(null, 'Resource with modules and topics updated successfully');
        });
    }
    /**
     * Delete the specified resource topic based on the incoming request data.
     *
     * @param \Illuminate\Http\Request $request  The HTTP request containing the topic ID or related data.
     *
     * @return array
     */
    public function deleteTopic(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $topicId = $this->decryptedValues($request->topic_id);
            $topic = ModuleResourceTopic::find($topicId);

            if (! $topic) {
                return $this->failedResponse('Topic not found');
            }
            $topic->delete();

            return $this->successResponse(null, 'Topic deleted successfully');
        });
    }
    /**
     * Retrieves all modules and topics for a user's subscribed resources.
     * Handles both single and multiple resource cases and returns a formatted response.
     *
     * @param int $userId
     *
     * @return array
     */
    public function allModulesList($userId)
    {
        return $this->runInTransaction(function () use ($userId) {
            if ($this->isAdminUser($userId)) {
                $resources = Resources::select('id as resource_id', 'resource_name')->get();
                $data = $this->formatMultipleResources($resources);
            } else {
                $resource = UserSubscription::where('user_id', $userId)
                    ->select('resource_id')
                    ->first();
                if (! $resource) {
                    return $this->failedResponse('No subscription found for the user.');
                }
                $data = $this->formatSingleResource($resource->resource_id);
            }

            return $this->successResponse($data, 'Modules with Topics List');
        });
    }
    /**
     * Create a new resource topic for the specified module.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request containing topic data.
     * @param int $moduleId The ID of the module to associate the resource topic with.
     *
     * @return array|null Returns an array containing the created topic ID, or null on failure.
     */
    public function updateResourceTopic(Request $request, int $moduleId): ?array
    {
        $videoUrl = $request->resource_type === '0' ? $request->video_url : null;
        $topicId = $this->decryptedValues($request->topic_id);

        ModuleResourceTopic::where('id', $topicId)->update([
            'module_resource_id' => $moduleId,
            'resource_topic' => $request->topic_name,
            'resource_type' => $request->resource_type,
            'description' => $request->description,
            'video_url' => $videoUrl,
        ]);

        return [
            'topicId' => $topicId,
        ];
    }
    /**
     * Processes all modules and their respective topics and files.
     *
     * @param array $modules The list of module data.
     * @param int $resourceId The ID of the resource these modules belong to.
     *
     * @return array|null Returns an array of upload errors if any occurred, otherwise null.
     */
    protected function handleModulesAndTopics(array $modules, int $resourceId): ?array
    {
        foreach ($modules as $moduleData) {
            $module = $this->createModule($moduleData, $resourceId);
            foreach ($moduleData['resources'] as $topicData) {
                $uploadError = $this->createTopicAndUploadFiles($module['moduleId'], $topicData);
                if ($uploadError) {
                    return $uploadError;
                }
            }
        }
        return null;
    }
    /**
     * Create a module using the provided module data and resource ID.
     *
     * @param array $moduleData
     * @param int $resourceId
     *
     * @return array
     */
    protected function createModule(array $moduleData, int $resourceId): array
    {
        $moduleRequest = new Request([
            'module_name' => $moduleData['moduleName'],
        ]);

        $module = ModuleResources::create([
            'resource_id' => $resourceId,
            'module_name' => $moduleRequest->module_name,
        ]);

        return [
            'moduleId' => $module->id,
        ];
    }
    /**
     * Creates a topic and uploads files if needed.
     *
     * @param int $moduleId The ID of the module to associate with the topic.
     * @param array $topicData The data for the topic.
     *
     * @return array|null Returns an array of upload errors if any occurred, otherwise null.
     */
    protected function createTopicAndUploadFiles(int $moduleId, array $topicData): ?array
    {
        $topicRequest = $this->buildTopicRequest($topicData, $moduleId);
        $resourceTopic = $this->createResourceTopic($topicRequest, $moduleId);

        if ($topicRequest->resource_type !== 0 && $resourceTopic) {
            $uploadError = $this->uploadFileService->uploadReourceFiles($topicRequest, $resourceTopic['topicId']);
            if ($uploadError) {
                throw new \Exception('File upload failed');
            }
        }
        return null;
    }
    /**
     * Build a request object for creating or updating a topic.
     *
     * @param array $topicData
     * @param int $moduleId
     *
     * @return \Illuminate\Http\Request
     */
    protected function buildTopicRequest(array $topicData, int $moduleId): Request
    {
        return new Request([
            'module_id' => $moduleId,
            'topic_name' => $topicData['topic'],
            'description' => $topicData['description'],
            'resource_type' => $topicData['type'],
            'video_url' => $topicData['video_url'] ?? '',
            'resource_file' => $topicData['resource_file'] ?? '',
            'topic_id' => $topicData['topic_id'] ?? '',
        ]);
    }
    /**
     * Get the integer resource type ID based on the given resource type string.
     *
     * @param string $type
     *
     * @return int
     */
    protected function getResourceType(string $type): int
    {
        return match (strtolower($type)) {
            'video' => 0,
            'pdf' => 1,
            'mp3' => 2,
            default => 0,
        };
    }
    /**
     * Checks if the given user has the ADMIN role.
     *
     * @param int $userId
     *
     * @return bool
     */
    protected function isAdminUser(int $userId): bool
    {
        return ModelHasRoles::where('model_id', $userId)
            ->where('role_id', ModelHasRoles::ADMIN)
            ->exists();
    }
    /**
     * Formats data for a single resource ID.
     * Includes encrypted ID and modules with topics.
     *
     * @param int $resourceId
     *
     * @return array
     */
    protected function formatSingleResource(int $resourceId): array
    {
        $moduleData = $this->getFormattedModulesByResourceId($resourceId);
        $modulesWithTopics = $this->mapModulesWithTopics($moduleData);

        return [
            'resourceId' => $this->encryptedValues($resourceId),
            'modules' => $modulesWithTopics,
        ];
    }
    /**
     * Formats data for multiple resource IDs.
     * Returns an array of structured items, each with encrypted ID and modules.
     *
     * @param \Illuminate\Support\Collection $resources
     *
     * @return array
     */
    protected function formatMultipleResources($resources): array
    {
        $data = [];

        foreach ($resources as $resource) {
            $resourceId = $resource->resource_id;
            $moduleData = $this->getFormattedModulesByResourceId($resourceId);
            $modulesWithTopics = $this->mapModulesWithTopics($moduleData);

            $data[] = [
                'resourceId' => $this->encryptedValues($resourceId),
                'resourceName' => $resource->resource_name,
                'modules' => $modulesWithTopics,
            ];
        }

        return $data;
    }
    /**
     * Processes all modules and their respective topics and files.
     *
     * @param array $modules The list of module data.
     *
     * @return array|null Returns an array of upload errors if any occurred, otherwise null.
     */
    protected function updateModulesAndTopics(array $modules): ?array
    {
        foreach ($modules as $moduleData) {
            $module = $this->updateModules($moduleData);
            foreach ($moduleData['resources'] as $topicData) {
                $uploadError = $this->updateTopicAndUploadFiles($module['moduleId'], $topicData);
                if ($uploadError) {
                    return $uploadError;
                }
            }
        }
        return null;
    }
    /**
     * Update a module using the provided module data and resource ID.
     *
     * @param array $moduleData
     *
     * @return array
     */
    protected function updateModules(array $moduleData): array
    {
        $moduleRequest = new Request([
            'module_name' => $moduleData['moduleName'],
            'module_id' => $moduleData['moduleId'],
        ]);
        $moduleId = $this->decryptedValues($moduleRequest->module_id);

        ModuleResources::where('id', $moduleId)->update([
            'module_name' => $moduleRequest->module_name,
        ]);

        return [
            'moduleId' => $moduleId,
        ];
    }
    /**
     * Creates a topic and uploads files if needed.
     *
     * @param int $moduleId The ID of the module to associate with the topic.
     * @param array $topicData The data for the topic.
     *
     * @return array|null Returns an array of upload errors if any occurred, otherwise null.
     */
    protected function updateTopicAndUploadFiles(int $moduleId, array $topicData): ?array
    {
        $topicRequest = $this->buildTopicRequest($topicData, $moduleId);
        $resourceTopic = $this->updateResourceTopic($topicRequest, $moduleId);

        if ($topicRequest->resource_type !== 0 && $resourceTopic) {
            $uploadError = $this->uploadFileService->uploadReourceFiles($topicRequest, $resourceTopic['topicId']);
            if ($uploadError) {
                throw new \Exception('File upload failed');
            }
        }
        return null;
    }
    /**
     * Retrieve and format modules related to a given resource ID.
     *
     * @param int $resourceId The ID of the resource.
     *
     * @return \Illuminate\Support\Collection A collection of formatted module data with encrypted IDs.
     */
    private function getFormattedModulesByResourceId(int $resourceId): \Illuminate\Support\Collection
    {
        $modules = ModuleResources::where('resource_id', $resourceId)->get();

        return $modules->map(function ($module) {
            return [
                'moduleId' => $this->encryptedValues($module->id),
                'moduleName' => $module->module_name,
            ];
        });
    }
    /**
     * Retrieve and format modules related to a given resource ID.
     *
     * @param int $resourceId The ID of the resource.
     *
     * @return \Illuminate\Support\Collection A collection of formatted module data with encrypted IDs.
     */
    private function getResourceTopic(int $moduleId): \Illuminate\Support\Collection
    {
        $topics = ModuleResourceTopic::where('module_resource_id', $moduleId)->get();

        return $topics->map(function ($topic) {
            return [
                'topicId' => $this->encryptedValues($topic->id),
                'topicName' => $topic->resource_topic,
                'resourceType' => $topic->resource_type,
                'description' => $topic->description,
                'filePath' => $topic->resource_path,
                'videoUrl' => $topic->video_url,
            ];
        });
    }
    /**
     * Map modules to their associated resource topics.
     *
     * @param \Illuminate\Support\Collection $moduleData A collection of modules with encrypted IDs.
     *
     * @return \Illuminate\Support\Collection The collection including modules and their topics.
     */
    private function mapModulesWithTopics(\Illuminate\Support\Collection $moduleData): \Illuminate\Support\Collection
    {
        return $moduleData->map(function ($module) {
            $moduleId = $this->decryptedValues($module['moduleId']);
            $topics = $this->getResourceTopic($moduleId);
            return [
                'moduleId' => $module['moduleId'],
                'moduleName' => $module['moduleName'],
                'topics' => $topics,
            ];
        });
    }
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validateResource(Request $request): ?array
    {
        $rules = [
            'resource_name' => 'required',
        ];
        $messages = [
            'resource_name.required' => 'Resource Name is required',
        ];

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validateResourceTopic(Request $request): ?array
    {
        $rules = [
            'topic_name' => 'required',
        ];
        $messages = [
            'topic_name.required' => 'Resource Topic is required',
        ];

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    private function updateResource(Request $request)
    {
        $resourceId = $this->decryptedValues($request->resource_id);

        Resources::where('id', $resourceId)->update([
            'resource_name' => $request->resource_name,
            'monthly_fee' => $request->monthly_amount,
            'annual_fee' => $request->annual_amount,
        ]);
    }
}
