<?php

namespace App\Services;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Cache;

class LanguageFieldTranslator
{
    const DEFAULT_LANGUAGE = 'en';

    const EXCLUDE_MODELS = [];

    private int $batchCount = 0;

    public array $log = [];

    function __construct(
        public int $batchLimit = 10
    )
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);
}

    /**
     * Fetches all models in the database
     * Then calls translateModel() for each model
     *
     * @return void
     */
    public function translateDatabase(): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Fetch all models
        $path = app_path('/Models');
        $models = $this->getModels($path);

        foreach ($models as $model) {
            if (in_array($model, self::EXCLUDE_MODELS)) continue;

            $this->translateModel('App\Models\\' . $model);
        }
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

                // Check if this field has been tried to be translated before within the last hour, if so, skip it to avoid unnecessary translation attempts
                $cacheKey = 'translation_attempt_' . $model . '_' . $field;

                if (Cache::has($cacheKey)) continue;

                Cache::tags(['translation_attempt'])->put($cacheKey, '1', now()->addHours(1));

                Cache::put('last_translation_column', $model . '_' . $field);
                Cache::put('last_translation_time', date('Y-m-d H:i:s'));

                $isHTML = in_array($languageAttribute, ['shop_description']);

                $this->log[] = $model . ' -> ' . $field . ' -> ' . $language->language_code;
                list($translation) = $translationController->translate([$defaultValue], self::DEFAULT_LANGUAGE, $language->language_code, $isHTML);

                if ($translation) {
                    $item->update([
                        $field => $translation
                    ]);
                }

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
     * @return array
     */
    public function getModels(string $path): array
    {
        $output = [];

        $files = scandir($path);

        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $filepath = $path . '/' . $file;

            if (is_dir($filepath)) {
                $output = array_merge($output, $this->getModels($filepath));
            } else {
                $output[] = str_replace('.php', '', $file);
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
}
