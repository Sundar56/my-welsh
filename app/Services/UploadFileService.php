<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Resources\Models\ModuleResourceTopic;
use App\Api\Admin\Modules\Settings\Models\Settings;
use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class UploadFileService
{
    use ApiResponse, TransactionWrapper;

    /**
     * Upload and validate resource files based on resource type.
     *
     * Handles uploading resource files (e.g., PDF or audio) for a given topic.
     * Validates the uploaded file according to its type before committing to the database.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing file and resource_type.
     * @param int $topicId The ID of the topic the resource is linked to.
     *
     * @return \Illuminate\Http\JsonResponse A success or error JSON response.
     */
    public function uploadReourceFiles(Request $request, int $topicId)
    {
        return $this->runInTransaction(function () use ($request, $topicId) {
            $validationErrors = $this->validateResourceByType($request);
            if ($validationErrors) {
                return $this->validationErrorResponse($validationErrors);
            }
            $this->uploadProfileImage($request, $topicId);

            return null;
        });
    }
    /**
     * Upload settings logo.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing file and resource_type.
     * @param int $settingId The settings ID for building the upload logo path.
     *
     * @return \Illuminate\Http\JsonResponse A success or error JSON response.
     */
    public function uploadLogoImage(Request $request, int $settingId)
    {
        return $this->runInTransaction(function () use ($request, $settingId) {
            $this->updateLogo($request, $settingId);

            return null;
        });
    }
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validatePdf(Request $request): ?array
    {
        $lang = $request->language ?? 'en';

        $rules = [
            'resource_file' => 'mimes:pdf|max:10240',
        ];
        $messages = [
            'resource_file.mimes' => trans('message.errors.resource_file_pdf_mimes', [], $lang),
            'resource_file.max' => trans('message.errors.resource_file_pdf_max', [], $lang),
        ];

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validateAudio(Request $request): ?array
    {
        $lang = $request->language ?? 'en';

        $rules = [
            'resource_file' => 'mimes:mp3,wav|max:10240',
        ];
        $messages = [
            'resource_file.mimes' => trans('message.errors.resource_file_audio_mimes', [], $lang),
            'resource_file.max' => trans('message.errors.resource_file_audio_max', [], $lang),
        ];

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * Validate resource input based on resource type (PDF or audio).
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|null Returns validation errors if any, or null if valid.
     */
    private function validateResourceByType(Request $request)
    {
        if ($request->resource_type === '1') {
            return $this->validatePdf($request);
        }
        return $this->validateAudio($request);
    }
    /**
     * Get the upload path based on resource type and topic ID.
     *
     * @param \Illuminate\Http\Request $request The request containing the resource_type.
     * @param int $id The topic ID used to build the path.
     *
     * @return string The full relative path for file upload.
     */
    private function getPath(Request $request, int $id): string
    {
        $type = (int) $request->resource_type;

        $paths = [
            1 => "/uploadassets/resources/pdf/{$id}/",
            2 => "/uploadassets/resources/audio/{$id}/",
        ];

        return $paths[$type] ?? '/uploadassets/resources/';
    }
    /**
     * Handles profile image upload and stores the file path in the user table.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request containing the file.
     * @param int $userId The ID of the user whose profile image is being updated.
     * @param int $topicId The ID used for building the upload path.
     *
     * @return void
     */
    private function uploadProfileImage(Request $request, int $topicId): void
    {
        $path = $this->getPath($request, $topicId);
        $this->createDirectoryIfNotExists($path);

        $file = $request->resource_file ?? null;

        if ($file) {
            $filePath = $this->uploadFile($file, $path, 'resource_');
            ModuleResourceTopic::where('id', $topicId)->update([
                'resource_path' => $filePath,
            ]);
        }
    }
    /**
     * Create a directory if it doesn't already exist.
     *
     * @param string $path Relative path from the public directory.
     *
     * @return void
     */
    private function createDirectoryIfNotExists(string $path): void
    {
        $fullPath = public_path($path);

        $this->ensureDirectory($fullPath);
    }
    /**
     * Ensure that the given directory exists. If it doesn't, create it with proper permissions.
     *
     * @param string $path The full path of the directory to check or create.
     *
     * @return void
     */
    private function ensureDirectory(string $path): void
    {
        if (! File::exists($path)) {
            File::makeDirectory($path, 0775, true);
        }
    }
    /**
     * Uploads a file to the specified public path with a given filename prefix.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded file instance.
     * @param string $path Relative path from the public directory where the file will be stored.
     * @param string $prefix Filename prefix to prepend (e.g., 'profile_', 'resource_').
     *
     * @return string The full relative path to the uploaded file.
     */
    private function uploadFile(\Illuminate\Http\UploadedFile $file, string $path, string $prefix): string
    {
        $fileName = $prefix . time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path($path), $fileName);

        return $path . $fileName;
    }
    /**
     * Handles logo image upload and stores the file path in the user table.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request containing the file.
     * @param int $settingId The settings ID for building the upload logo path.
     *
     * @return void
     */
    private function updateLogo(Request $request, int $settingId): void
    {
        $path = "/uploadassets/settings/logo/{$settingId}/";
        $this->createDirectoryIfNotExists($path);

        $file = $request->logo ?? null;

        if ($file) {
            $filePath = $this->uploadFile($file, $path, 'logo_');
            Settings::where('id', $settingId)->update([
                'logo' => $filePath,
            ]);
        }
    }
}
