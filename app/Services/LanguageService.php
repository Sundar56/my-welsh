<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;

class LanguageService
{
    use TransactionWrapper, ApiResponse;

    /**
     * Handle add customer and assign a role.
     *
     * @param Request $request
     *
     * @return array
     */
    public function getLanguages(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $languageCode = $request->language_code ?? 'en';
            App::setLocale($languageCode);

            $translations = $this->loadLanguageFile($languageCode);
            if ($translations === null) {
                return $this->errorResponse('Language not supported', 404);
            }
            $customTranslations = $this->extractCustomTranslations($translations);

            return $this->buildLanguageResponse($request, $translations, $customTranslations);
        });
    }
    /**
     * Load translation file by language code.
     *
     * @param string $languageCode
     *
     * @return array|null
     */
    protected function loadLanguageFile(string $languageCode): ?array
    {
        $filePath = resource_path("lang/{$languageCode}/message.php");

        if (! File::exists($filePath)) {
            return null;
        }

        return include $filePath;
    }
    /**
     * Extract custom translation keys from full translations.
     *
     * @param array $translations
     *
     * @return array
     */
    protected function extractCustomTranslations(array $translations): array
    {
        $customKeys = ['common'];
        $customTranslations = [];

        foreach ($customKeys as $key) {
            $value = data_get($translations, $key);
            if ($value !== null) {
                $customTranslations[$key] = $value;
            }
        }

        return $customTranslations;
    }
    /**
     * Build the language response, either for a specific group or full data.
     *
     * @param Request $request
     * @param array $translations
     * @param array $customTranslations
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function buildLanguageResponse(Request $request, array $translations, array $customTranslations)
    {
        $group = $request->input('group');

        if ($group) {
            $groupData = data_get($translations, $group);
            if ($groupData === null) {
                return $this->errorResponse("Group '{$group}' not found", 404);
            }

            return $this->successResponse([
                'group' => [$group => $groupData],
                'common_keywords' => $customTranslations,
            ], "Group '{$group}' with custom groups returned");
        }

        return $this->successResponse($translations, 'All language data with custom groups returned');
    }
}
