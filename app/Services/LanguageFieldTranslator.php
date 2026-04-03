<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\TranslationController;
use App\Services\AI\AIService;
use App\Utilities\MetaDataStorage;

class LanguageFieldTranslator
{
    const DEFAULT_LANGUAGE = 'en';

    const EXCLUDE_MODELS = [];

    const MAX_TRANSLATION_FAILURES = 3;

    private AIService $aiService;

    private int $batchCount = 0;

    private array $translationExcludes = [];

    private array $batchRequests = [];

    function __construct(
        public int $batchLimit
    )
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $this->aiService = new AIService('claude-sonnet-4-6'); // TODO: Load model from some config

        $globalExcludes = ConfigController::getConfig('translation_excludes');
        $globalExcludes = preg_split("/\r\n|\n|\r/", $globalExcludes);
        $this->translationExcludes = array_merge($globalExcludes, TranslateExcludeService::getAll());
    }

    /**
     * Fetches all models in the database
     * Then calls translateModel() for each model
     *
     * @return void
     */
    public function translateDatabase(): void
    {
        $currentStep = (int) ConfigController::getConfig('translate_database_current_step');

        switch ($currentStep) {
            case 0:
                $this->startNewBatch();
                break;

            case 1:
                $this->processBatch1();
                 break;

            case 2:
                $this->processBatch2();
                break;
        }
    }

    public function startNewBatch(): void
    {
        // Fetch all models
        $path = app_path('/Models');
        $models = $this->getModels($path);

        foreach ($models as $model) {
            if (in_array($model, self::EXCLUDE_MODELS)) continue;

            $this->translateModel('App\Models\\' . $model);
        }

        if (count($this->batchRequests) === 0) {
            return;
        }

        $response = $this->aiService->createMessageBatch($this->batchRequests);
        $batchId = $response['id'] ?? null;

        if (!$batchId) {
            echo 'Failed to create message batch.' . PHP_EOL;
            return;
        }

        ConfigController::setConfigs([
            'translate_database_current_step' => '1',
            'translate_database_batch_id' => $batchId,
        ]);

        echo 'New batch created.' . PHP_EOL;
    }

    public function processBatch1(): void
    {
        $batchId = ConfigController::getConfig('translate_database_batch_id');
        if (!$batchId) {
            ConfigController::setConfigs(['translate_database_current_step' => '0']);
            return;
        }

        $status = $this->aiService->getMessageBatch($batchId);
        if ($status['processing_status'] !== 'ended') {
            echo 'Process 1 waiting for batch to complete.' . PHP_EOL;
            return;
        }

        $promptController = new PromptController();
        $verifyPrompt = $promptController->getBySystemCode('translate_3_step_verify');

        $results = $this->aiService->getBatchTexts($batchId);
        foreach ($results as $customID => $text) {
            $metaDataKey = 'aibatch:' . $customID;
            $metaData = MetaDataStorage::get($metaDataKey);

            if (empty($metaData) || !isset($metaData['inputs'])) {
                MetaDataStorage::delete($metaDataKey);
                continue;
            }

            // Skip failed translations
            if (!$text) {
                $this->recordTranslationFailure($metaData);
                MetaDataStorage::delete($metaDataKey);
                continue;
            }

            $inputs = $metaData['inputs'];
            $inputs['source_text'] = $inputs['string'];
            $inputs['translated_text'] = $text;

            $system = $promptController->replaceInputs($verifyPrompt->system, $inputs);
            $message = $promptController->replaceInputs($verifyPrompt->message, $inputs);

            unset($metaData['inputs']);
            $metaData['unverified_text'] = $text;

            $this->batchRequests[] = [
                'system' => $system,
                'message' => $message,
                'meta_data' => $metaData
            ];

            MetaDataStorage::delete($metaDataKey);
        }

        if (count($this->batchRequests) === 0) {
            echo 'Process 1 completed with no valid translations.' . PHP_EOL;
            ConfigController::setConfigs(['translate_database_current_step' => '0']);
            return;
        }

        $response = $this->aiService->createMessageBatch($this->batchRequests);
        $batchId = $response['id'] ?? null;

        if (!$batchId) {
            echo 'Process 1 failed to create verification batch, saving unverified translations.' . PHP_EOL;
            $this->saveUnverifiedTranslations();
            ConfigController::setConfigs(['translate_database_current_step' => '0']);
            return;
        }

        ConfigController::setConfigs([
            'translate_database_current_step' => '2',
            'translate_database_batch_id' => $batchId,
        ]);

        echo 'Process 1 completed.' . PHP_EOL;
    }

    public function processBatch2(): void
    {
        $batchId = ConfigController::getConfig('translate_database_batch_id');
        if (!$batchId) {
            ConfigController::setConfigs(['translate_database_current_step' => '0']);
            return;
        }

        $status = $this->aiService->getMessageBatch($batchId);
        if ($status['processing_status'] !== 'ended') {
            echo 'Process 2 waiting for batch to complete.' . PHP_EOL;
            return;
        }

        $results = $this->aiService->getBatchTexts($batchId);
        foreach ($results as $customID => $text) {
            $metaDataKey = 'aibatch:' . $customID;
            $metaData = MetaDataStorage::get($metaDataKey);

            if (empty($metaData)) {
                MetaDataStorage::delete($metaDataKey);
                continue;
            }

            $model = $metaData['model'];
            $primaryKey = $metaData['primary_key'];
            $primaryKeyValue = $metaData['primary_key_value'];
            $column = $metaData['column'];

            // Use verified text, fall back to unverified translation from batch 1
            $translatedText = $text ?: ($metaData['unverified_text'] ?? null);

            if (!$translatedText) {
                $this->recordTranslationFailure($metaData);
                MetaDataStorage::delete($metaDataKey);
                continue;
            }

            $obj = (new $model)->where($primaryKey, $primaryKeyValue)->first();
            if ($obj) {
                $obj->{$column} = $translatedText;
                $obj->save();
            }

            MetaDataStorage::delete($metaDataKey);
        }

        ConfigController::setConfigs(['translate_database_current_step' => '0']);

        echo 'Process 2 completed.' . PHP_EOL;
    }

    /**
     * Generates a translation for empty language fields given that the base language field is not empty
     *
     * @param $model
     * @return void
     */
    public function translateModel($model): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Load languages
        $languageController = new LanguageController();
        $languages = $languageController->getAllLanguages();

        // Find all language attributes
        $languageAttributes = $this->getLanguageAttributes($model);

        if (!$languageAttributes) {
            return;
        }

        foreach ($languageAttributes as $languageAttribute) {
            $this->translateAttribute($model, $languageAttribute, $languages);

            if ($this->isBatchFulfilled()) {
                break;
            }
        }
    }

    /**
     * Returns all language attributes for a model
     *
     * @param $model
     * @return array
     */
    public function getLanguageAttributes($model): array
    {
        $object = (new $model)->first();

        if (!$object) {
            return [];
        }

        $attributes = $object->attributesToArray();

        $languageAttributes = array_filter($attributes, function ($key) {
            return str_ends_with($key, ('_' . self::DEFAULT_LANGUAGE));
        }, ARRAY_FILTER_USE_KEY);

        // Remove language suffix from attributes
        $result = [];

        foreach ($languageAttributes as $key => $value) {
            // remove the last 3 characters
            $result[] = substr($key, 0, -3);
        }

        return $result;
    }

    /**
     * Translates the given language attribute for the model
     *
     * @param $model
     * @param $languageAttribute
     * @param $languages
     * @return void
     */
    public function translateAttribute($model, $languageAttribute, $languages): void
    {
        $translationController = new TranslationController();

        $attributes = [];

        foreach ($languages as $language) {
            if ($language->language_code == self::DEFAULT_LANGUAGE
                || $language->language_code == 'is') {
                continue;
            }

            $attributes[] = $languageAttribute . '_' . $language->language_code;
        }

        // Fetch items that needs to be translated
        $defaultCol = $languageAttribute . '_' . self::DEFAULT_LANGUAGE;

        try {
            $items = (new $model)
                ->whereNotNull($defaultCol)
                ->where($defaultCol, '!=', '')
                ->where(function ($subQuery) use ($attributes) {
                    foreach ($attributes as $attribute) {
                        $subQuery->orWhereNull($attribute)
                            ->orWhere($attribute, '=', '');
                    }

                    return $subQuery;
                })
                ->limit($this->batchLimit)
                ->get();
        } catch (\Throwable $e) {
            return;
        }

        foreach ($items as $item) {
            foreach ($languages as $language) {
                if ($language->language_code == self::DEFAULT_LANGUAGE
                    || $language->language_code == 'is') {
                    continue;
                }

                $field = $languageAttribute . '_' . $language->language_code;

                $defaultValue = $item->{$languageAttribute . '_' . self::DEFAULT_LANGUAGE};

                if ($item->{$field}) {
                    continue;
                }

                if ($this->hasExceededFailureLimit($model, $item->getKey(), $field)) {
                    continue;
                }

                // TODO: Move models to global init
                $promptController = new PromptController();

                $corePrompt = $promptController->getBySystemCode('translate_3_step_core');
                $languagePrompt = $promptController->getBySystemCode('translate_3_step_core_' . $language->language_code);

                $system = $corePrompt->system;
                $message = $corePrompt->message;
                $languageRules = ($languagePrompt->message ?? '');

                $inputs = [
                    'sourceLang' => self::DEFAULT_LANGUAGE,
                    'targetLang' => $language->language_code,
                    'GLOSSARY' => implode(PHP_EOL, $this->translationExcludes),
                    'language_rules' => $languageRules,
                    'string' => $defaultValue
                ];

                $system = $promptController->replaceInputs($system, $inputs);
                $message = $promptController->replaceInputs($message, $inputs);

                $this->batchRequests[] = [
                    'system' => $system,
                    'message' => $message,
                    'meta_data' => [
                        'model' => $model,
                        'primary_key' => $item->getKeyName(),
                        'primary_key_value' => $item->getKey(),
                        'column' => $field,
                        'inputs' => $inputs
                    ]
                ];

                $this->batchCount++;
                if ($this->isBatchFulfilled()) {
                    return;
                }

            }
        }
    }

    /**
     * Returns all models in the given path
     *
     * @param string $path
     * @param string $prefix Namespace prefix for subdirectories
     * @return array
     */
    public function getModels(string $path, string $prefix = ''): array
    {
        $output = [];

        $files = scandir($path);

        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $filepath = $path . '/' . $file;

            if (is_dir($filepath)) {
                $output = array_merge($output, $this->getModels($filepath, $prefix . $file . '\\'));
            } else {
                $output[] = $prefix . str_replace('.php', '', $file);
            }
        }

        return $output;
    }

    /**
     * Returns true if the current batch has reached the set limit
     *
     * @return bool
     */
    private function isBatchFulfilled(): bool
    {
        return $this->batchCount >= $this->batchLimit;
    }

    private function getFailureKey(string $model, mixed $primaryKeyValue, string $column): string
    {
        return 'translate_fail:' . $model . ':' . $primaryKeyValue . ':' . $column;
    }

    private function recordTranslationFailure(array $metaData): void
    {
        $key = $this->getFailureKey($metaData['model'], $metaData['primary_key_value'], $metaData['column']);
        $existing = MetaDataStorage::get($key);
        $count = ($existing['count'] ?? 0) + 1;
        MetaDataStorage::set($key, ['count' => $count]);
    }

    private function hasExceededFailureLimit(string $model, mixed $primaryKeyValue, string $column): bool
    {
        $key = $this->getFailureKey($model, $primaryKeyValue, $column);
        $existing = MetaDataStorage::get($key);
        return ($existing['count'] ?? 0) >= self::MAX_TRANSLATION_FAILURES;
    }

    /**
     * Fallback: save unverified translations directly when verification batch creation fails
     */
    private function saveUnverifiedTranslations(): void
    {
        foreach ($this->batchRequests as $request) {
            $metaData = $request['meta_data'] ?? [];
            $text = $metaData['unverified_text'] ?? null;

            if (empty($metaData) || !$text) {
                continue;
            }

            $model = $metaData['model'];
            $primaryKey = $metaData['primary_key'];
            $primaryKeyValue = $metaData['primary_key_value'];
            $column = $metaData['column'];

            $obj = (new $model)->where($primaryKey, $primaryKeyValue)->first();
            if ($obj) {
                $obj->{$column} = $text;
                $obj->save();
            }
        }
    }
}
