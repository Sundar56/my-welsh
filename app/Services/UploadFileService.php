<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Resources\Models\ModuleResourceTopic;
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
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validatePdf(Request $request): ?array
    {
        $rules = [
            'resource_file' => 'mimes:pdf|max:10240',
        ];
        $messages = [
            'resource_file.mimes' => 'The file must be a PDF.',
            'resource_file.max' => 'The PDF must not exceed 10MB.',
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
        $rules = [
            'resource_file' => 'mimes:mp3,wav|max:10240',
        ];
        $messages = [
            'resource_file.mimes' => 'Audio file must be an MP3 or WAV format.',
            'resource_file.max' => 'Audio file must not exceed 10MB.',
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
        switch ($type) {
            case 1:
                $path = "/uploadassets/resources/pdf/{$id}/";
                break;

            case 2:
                $path = "/uploadassets/resources/audio/{$id}/";
                break;

            default:
                $path = '/uploadassets/resources/';
                break;
        }
        return $path;
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
        if ($request->resource_file) {
            $file = $request->resource_file;

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

        if (! File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0775, true);
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
}
