<?php

namespace App\Services;

use App\Http\Controllers\LanguageController;
use App\Models\Article;
use App\Models\Translation;
use App\Models\TranslationService;
use App\Services\AI\AIService;
use Illuminate\Support\Facades\DB;

class TranslationServiceManager
{
    const BASE_LOCALE = 'en';

    /**
     * Execute a batch of translations.
     * Returns true if the entire batch was fulfilled, otherwise false.
     * @param int $batchSize
     * @return bool
     */
    public function executeBatch(int $batchSize): bool
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $batchCount = 0;

        $services = $this->getAllServices();

        $languages = (new LanguageController())->getAllLanguages();

        $tables = [
            'articles' => [
                'shop_title',
                'shop_description'
            ],
        ];

        foreach ($services as $service) {
            foreach ($languages as $language) {

                foreach ($tables as $table => $fields) {
                    foreach ($fields as $field) {

                        $baseField = $field . '_' . self::BASE_LOCALE;

                        $translatedIDs = DB::table('translations')
                            ->select('table_id')
                            ->where('table', $table)
                            ->where('field', $field)
                            ->where('language_code', $language->language_code)
                            ->where('service_id', $service->id)
                            ->get()
                            ->pluck('table_id')
                            ->toArray();

                        $rows = DB::table($table)
                            ->select('id', $baseField)
                            ->where($baseField, '!=', '')
                            ->whereNotIn('id', $translatedIDs)
                            ->get();

                        foreach ($rows as $row) {
                            $success = $this->translateAndStore(
                                $table,
                                $field,
                                $row->id,
                                $language->language_code,
                                $service,
                                $row->{$baseField},
                            );

                            if ($success) {
                                $batchCount++;
                            }

                            if ($batchCount >= $batchSize) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public function translateAndStore(string $table, string $field, int $tableID, string $languageCode, TranslationService $service, string $text): bool
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        switch ($service->name) {
            case 'openai':
                $AIService = new AIService('gpt-4o');
                $translatedText = $AIService->translate($text, self::BASE_LOCALE, $languageCode);
                break;

            case 'claude':
                $AIService = new AIService('claude-3-5-sonnet-20240620');
                $translatedText = $AIService->translate($text, self::BASE_LOCALE, $languageCode);
                break;

            default:
                $translatedText = '';
                break;
        }

        if (!$translatedText) {
            return false;
        }

        $this->storeTranslation($table, $field, $tableID, $translatedText, $languageCode, $service->id);

        return true;
    }

    public function storeTranslation(string $table, string $field, int $tableID, string $translatedText, string $languageCode, int $serviceID)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $translation = self::getTranslation($table, $field, $tableID, $languageCode, $serviceID);

        if ($translation) {
            $translation->update(['translation' => $translatedText]);
        }
        else {
            Translation::create([
                'table' => $table,
                'table_id' => $tableID,
                'field' => $field,
                'language_code' => $languageCode,
                'service_id' => $serviceID,
                'translation' => $translatedText
            ]);
        }
    }

    public function getAllServices()
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        return TranslationService::all();
    }

    public static function getTranslation(string $table, string $field, string $tableID, string $languageCode, int $serviceID)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service static method.', $__serviceLogContext);

        return Translation::where('table', $table)
            ->where('table_id', $tableID)
            ->where('field', $field)
            ->where('language_code', $languageCode)
            ->where('service_id', $serviceID)
            ->first();
    }
}
