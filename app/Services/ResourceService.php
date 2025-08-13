<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Resources\Models\ModuleResources;
use App\Api\Admin\Modules\Resources\Models\ModuleResourceTopic;
use App\Api\Admin\Modules\Resources\Models\Resources;
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
            $lang = $request->language ?? 'en';

            return $this->successResponse(null, trans('message.success.resource_created', [], $lang));
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
            'type' => Resources::DEFAULT,
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
                'topicTypeMap' => config('custom.topic_type'),
            ];
            $data = array_merge($resourceInfo, $modulesWithTopics);
            $lang = $request->language ?? 'en';

            return $this->successResponse($data, trans('message.success.resource_details', [], $lang));
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
            // $validationErrors = $this->validateResource($request);
            // if ($validationErrors) {
            //     return $this->validationErrorResponse($validationErrors);
            // }
            // $this->updateResource($request);
            $uploadError = $this->updateModulesAndTopics($request->modules);
            if ($uploadError) {
                return $uploadError;
            }
            $lang = $request->language ?? 'en';

            return $this->successResponse(null, trans('message.success.resource_updated', [], $lang));
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
            $lang = $request->language ?? 'en';

            if (! $topic) {
                return $this->failedResponse(trans('message.error.no_subscription', [], $lang));
            }
            $topic->delete();

            return $this->successResponse(null, trans('message.success.topic_deleted', [], $lang));
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
     * Get all modules list for a user within a database transaction.
     *
     * @param int|string $userId The ID of the user.
     *
     * @return mixed The result of the transactional modules list retrieval.
     */
    public function allModulesList($userId)
    {
        return $this->runInTransaction(function () use ($userId) {
            return $this->getModulesListForUser($userId);
        });
    }
    /**
     * Handle edit resource with module name.
     *
     * @param Request $request
     *
     * @return array
     */
    public function editResourceNew(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $lang = $request->language ?? 'en';
            $resourceId = $this->decryptedValues($request->resource_id);
            $moduleIdEncrypted = $request->moduleId ?? null;
            $moduleName = $request->moduleName ?? null;
            $decryptedModuleId = $moduleIdEncrypted ? $this->decryptedValues($moduleIdEncrypted) : null;
            $modules = $request->modules;

            $moduleId = $this->createOrUpdateModule($decryptedModuleId, $moduleName, $resourceId);

            foreach ($modules as $moduleData) {
                $this->updateTopicsNew($moduleData['resources'] ?? [], $moduleId);
            }
            return $this->successResponse(null, trans('message.success.resource_updated', [], $lang));
        });
    }
    /**
     * Delete the specified resource topic based on the incoming request data.
     *
     * @param \Illuminate\Http\Request $request  The HTTP request containing the topic ID or related data.
     *
     * @return array
     */
    public function deleteTopicFile(Request $request): array
    {
        return $this->runInTransaction(function () use ($request) {
            $topicId = $this->decryptedValues($request->topic_id);
            $topic = ModuleResourceTopic::find($topicId);
            $lang = $request->language ?? 'en';

            if (! $topic) {
                return $this->failedResponse(trans('message.error.no_subscription', [], $lang));
            }
            $topic->resource_path = null;
            $topic->save();

            return $this->successResponse(null, trans('message.success.topic_deleted', [], $lang));
        });
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
        return $this->processModulesAndTopics(
            $modules,
            fn ($moduleData) => $this->createModule($moduleData, $resourceId),
            fn ($moduleId, $topicData) => $this->createTopicAndUploadFiles($moduleId, $topicData)
        );
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
            'topic_id' => $topicData['topic_id'] ?? null,
        ]);
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
        return $this->processModulesAndTopics(
            $modules,
            fn ($moduleData) => $this->updateModules($moduleData),
            fn ($moduleId, $topicData) => $this->updateTopicAndUploadFiles($moduleId, $topicData)
        );
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
        $moduleName = $moduleData['moduleName'] ?? null;
        $moduleIdEncrypted = $moduleData['moduleId'] ?? null;
        $moduleId = $moduleIdEncrypted ? $this->decryptedValues($moduleIdEncrypted) : null;

        $module = $moduleId
            ? tap(ModuleResources::find($moduleId))->update(['module_name' => $moduleName])
            : ModuleResources::create(['module_name' => $moduleName]);

        return [
            'moduleId' => $module->id,
        ];
    }
    /**
     * Processes an array of modules and their topics.
     *
     * @param array $modules An array of modules where each module contains its data and topics (resources).
     * @param callable $moduleHandler A callable function or method to handle the module creation or update.
     *                                It receives the module data as an argument.
     * @param callable $topicHandler A callable function or method to handle the topic creation or update, and file uploads.
     *                                It receives the module ID and topic data as arguments.
     *
     * @return array|null Returns null if all modules and topics are processed successfully.
     *                   Returns an array representing the error if any occurs during topic processing.
     */
    protected function processModulesAndTopics(array $modules, callable $moduleHandler, callable $topicHandler): ?array
    {
        foreach ($modules as $moduleData) {
            $module = $moduleHandler($moduleData);
            foreach ($moduleData['resources'] as $topicData) {
                $uploadError = $topicHandler($module['moduleId'], $topicData);
                if ($uploadError) {
                    return $uploadError;
                }
            }
        }
        return null;
    }
    /**
     * Creates or updates a topic and uploads associated files.
     *
     * @param int $moduleId The ID of the module to which the topic belongs.
     * @param array $topicData The data for the topic, including any associated files.
     * @param bool $isCreate Flag to determine whether the operation is creating (true) or updating (false) a topic.
     *
     * @return array|null Returns null if the operation is successful. If an error occurs, an exception will be thrown.
     */
    protected function processTopicAndUploadFiles(int $moduleId, array $topicData, bool $isCreate): ?array
    {
        $topicRequest = $this->buildTopicRequest($topicData, $moduleId);
        $resourceTopic = $isCreate
            ? $this->createResourceTopic($topicRequest, $moduleId)
            : $this->updateResourceTopic($topicRequest, $moduleId);

        if ($topicRequest->resource_type !== 0 && $resourceTopic) {
            $uploadError = $this->uploadFileService->uploadReourceFiles($topicRequest, $resourceTopic['topicId']);
            if ($uploadError) {
                throw new \Exception('File upload failed');
            }
        }
        return null;
    }
    /**
     * Creates a topic and uploads associated files.
     *
     * @param int $moduleId The ID of the module to which the topic belongs.
     * @param array $topicData The data for the topic, including any associated files.
     *
     * @return array|null Returns null if the creation and file upload are successful. If an error occurs, an exception is thrown.
     */
    protected function createTopicAndUploadFiles(int $moduleId, array $topicData): ?array
    {
        return $this->processTopicAndUploadFiles($moduleId, $topicData, true);
    }
    /**
     * Updates a topic and uploads associated files.
     *
     * @param int $moduleId The ID of the module to which the topic belongs.
     * @param array $topicData The data for the topic, including any associated files.
     *
     * @return array|null Returns null if the update and file upload are successful. If an error occurs, an exception is thrown.
     */
    protected function updateTopicAndUploadFiles(int $moduleId, array $topicData): ?array
    {
        return $this->processTopicAndUploadFiles($moduleId, $topicData, false);
    }
    /**
     * Creates a new module or updates an existing one with the given name and resource ID.
     *
     * @param int|null $moduleId The ID of the module to update, or null to create a new module.
     * @param string $moduleName The name of the module.
     * @param int $resourceId The ID of the resource to associate with the module.
     *
     * @return int The ID of the created or updated module.
     */
    protected function createOrUpdateModule(?int $moduleId, string $moduleName, int $resourceId): int
    {
        if ($moduleId && ($module = ModuleResources::find($moduleId))) {
            $module->update(['module_name' => $moduleName]);
            return $module->id;
        }

        $module = ModuleResources::create([
            'module_name' => $moduleName,
            'resource_id' => $resourceId,
        ]);

        return $module->id;
    }
    /**
     * Updates the topics for a given module with the provided resources.
     *
     * @param array $resources An array of resource data to update the topics with.
     * @param int $moduleId The ID of the module whose topics are being updated.
     *
     * @return void
     */
    protected function updateTopicsNew(array $resources, int $moduleId): void
    {
        foreach ($resources as $resource) {
            $this->processSingleResource($resource, $moduleId);
        }
    }
    /**
     * Process a single resource: create or update topic, and upload files if needed.
     *
     * @param array $resource
     * @param int $moduleId
     *
     * @return void
     *
     * @throws \Exception
     */
    private function processSingleResource(array $resource, int $moduleId): void
    {
        $topicRequest = $this->buildTopicRequest($resource, $moduleId);

        $resourceTopic = $this->handleResourceTopic($resource, $topicRequest, $moduleId);

        if ($this->requiresFileUpload($topicRequest, $resourceTopic)) {
            $this->handleFileUpload($topicRequest, $resourceTopic['topicId']);
        }
    }

    /**
     * Create or update the resource topic based on presence of topic_id.
     *
     * @param array $resource
     * @param mixed $topicRequest
     * @param int $moduleId
     *
     * @return mixed
     */
    private function handleResourceTopic(array $resource, $topicRequest, int $moduleId)
    {
        if (! isset($resource['topic_id']) || $resource['topic_id'] === null || $resource['topic_id'] === '') {
            return $this->createResourceTopic($topicRequest, $moduleId);
        }

        return $this->updateResourceTopic($topicRequest, $moduleId);
    }

    /**
     * Determine if a file upload is required.
     *
     * @param mixed $topicRequest
     * @param mixed $resourceTopic
     *
     * @return bool
     */
    private function requiresFileUpload($topicRequest, $resourceTopic): bool
    {
        return (int) $topicRequest->resource_type !== 0 && $resourceTopic;
    }
    /**
     * Handle the file upload and throw if it fails.
     *
     * @param mixed $topicRequest
     * @param int $topicId
     *
     * @throws \Exception
     */
    private function handleFileUpload($topicRequest, int $topicId): void
    {
        $uploadError = $this->uploadFileService->uploadReourceFiles($topicRequest, $topicId);

        if ($uploadError) {
            throw new \Exception('File upload failed');
        }
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
        $lang = $request->language ?? 'en';
        $rules = [
            'resource_name' => 'required',
        ];
        $messages = [
            'resource_name.required' => trans('message.resources.resource_name_required', [], $lang),
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
    /**
     * Updates a resource based on the provided request data.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request containing the data to update the resource.
     *
     * @return \Illuminate\Http\Response|mixed Returns a response or any data that represents the result of the update operation.
     *         This may include success messages, error messages, or other relevant data after updating the resource.
     */
    private function updateResource(Request $request)
    {
        $resourceId = $this->decryptedValues($request->resource_id);

        Resources::where('id', $resourceId)->update([
            'resource_name' => $request->resource_name,
            'monthly_fee' => $request->monthly_amount,
            'annual_fee' => $request->annual_amount,
        ]);
    }
    /**
     * Retrieve modules list for a specific user.
     *
     * Determines if the user is an admin or regular user,
     * and returns the appropriate modules list.
     *
     * @param int|string $userId The ID of the user.
     *
     * @return mixed The formatted modules list response.
     */
    private function getModulesListForUser($userId)
    {
        if ($this->isAdminUser($userId)) {
            return $this->getAdminModules();
        }

        return $this->getUserModules($userId);
    }
    /**
     * Retrieve modules list for an admin user.
     *
     * Fetches all resources and formats them for admin view.
     *
     * @return mixed The formatted success response with all modules.
     */
    private function getAdminModules()
    {
        $resources = Resources::select('id as resource_id', 'resource_name')->get();
        $data = $this->formatMultipleResources($resources);
        $lang = $request->language ?? 'en';

        return $this->successResponse($data, trans('message.success.module_topic_list', [], $lang));
    }
    /**
     * Retrieve modules list for a regular user.
     *
     * Checks user subscription and returns associated resource,
     * or a failure response if none found.
     *
     * @param int|string $userId The ID of the user.
     *
     * @return mixed Success response with single resource or failure response.
     */
    private function getUserModules($userId)
    {
        $resource = UserSubscription::where('user_id', $userId)
            ->select('resource_id')
            ->first();

        if (! $resource) {
            $lang = $request->language ?? 'en';
            return $this->failedResponse(trans('message.error.no_subscription', [], $lang));
        }

        $data = $this->formatSingleResource($resource->resource_id);
        return $this->successResponse($data, 'Modules with Topics List');
    }
}
