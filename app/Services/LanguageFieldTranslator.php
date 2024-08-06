<?php

namespace App\Services;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MetaDataController;
use App\Http\Controllers\TranslationController;

class LanguageFieldTranslator
{
    const DEFAULT_LANGUAGE = 'en';

    private int $batchCount = 0;

    function __construct(
        public int $batchLimit = 10
    )
    {}

    /**
     * Fetches all models in the database
     * Then calls translateModel() for each model
     *
     * @return void
     */
    public function translateDatabase(): void
    {
        // Fetch all models
        $path = app_path('/Models');
        $models = $this->getModels($path);

        foreach ($models as $model) {
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
    private function getLanguageAttributes($model): array
    {
        $object = (new $model)->first();

        if (!$object) {
            return [];
        }

        $attributes = $object->attributesToArray();

        $languageAttributes = array_filter($attributes, function ($key) {
            return str_contains($key, ('_' . self::DEFAULT_LANGUAGE));
        }, ARRAY_FILTER_USE_KEY);

        // Remove language suffix from attributes
        $result = [];

        foreach ($languageAttributes as $key => $value) {
            $result[] = str_replace(('_' . self::DEFAULT_LANGUAGE), '', $key);
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
    private function translateAttribute($model, $languageAttribute, $languages): void
    {
        $translationController = new TranslationController();

        $attributes = [];

        foreach ($languages as $language) {
            if ($language->language_code == self::DEFAULT_LANGUAGE) {
                continue;
            }

            $attributes[] = $languageAttribute . '_' . $language->language_code;
        }

        // Fetch items that needs to be translated
        $items = (new $model)
            ->where($languageAttribute . '_' . self::DEFAULT_LANGUAGE, '!=', '')
            ->where($languageAttribute . '_' . self::DEFAULT_LANGUAGE, '!=', null)
            ->where(function ($subQuery) use ($attributes) {
                foreach ($attributes as $attribute) {
                    $subQuery->orWhere($attribute, '=', '');
                    $subQuery->orWhere($attribute, '=', null);
                }

                return $subQuery;
            })
            ->limit($this->batchLimit)
            ->get();

        foreach ($items as $item) {
            foreach ($languages as $language) {

                $field = $languageAttribute . '_' . $language->language_code;

                $defaultValue = $item->{$languageAttribute . '_' . self::DEFAULT_LANGUAGE};

                if ($item->{$field}) {
                    continue;
                }

                $isHTML = in_array($languageAttribute, ['shop_description']);

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
    private function getModels(string $path): array
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
