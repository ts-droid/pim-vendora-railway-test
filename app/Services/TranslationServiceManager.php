<?php

namespace App\Services;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\TranslationController;
use App\Models\Translation;
use App\Models\TranslationService;
use Illuminate\Support\Facades\DB;

class TranslationServiceManager
{
    const BASE_LANGUAGE = 'en';

    private array $locales;

    private int $batchSize = 0;
    private int $batchCount = 0;
    private bool $batchCompleted = false;

    private TranslationService $service;

    private TranslationController $translationController;

    function __construct(
        TranslationService $service,
        int $batchSize
    )
    {
        $this->batchSize = $batchSize;
        $this->service = $service;

        $this->translationController = new TranslationController();

        $languageController = new LanguageController();

        $languages = $languageController->getAllLanguages();
        foreach ($languages as $language) {
            $this->locales[] = $language->language_code;
        }
    }

    public function translateDatabase()
    {
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map('current', $tables);

        foreach ($tableNames as $tableName) {
            $this->translateTable($tableName);

            if ($this->batchCompleted) {
                return;
            }
        }
    }

    private function translateTable(string $tableName)
    {
        $columns = DB::select("SHOW COLUMNS FROM $tableName");
        $columnNames = array_map(function($column) {
            return $column->Field;
        }, $columns);

        // Extract language columns
        $languageColumns = array_filter($columnNames, function($column) {
            return str_ends_with($column, '_' . self::BASE_LANGUAGE);
        });

        if (!$languageColumns) {
            return;
        }

        // Remove language suffix
        $languageColumns = array_map(function($column) {
            return substr($column, 0, -3);
        }, $languageColumns);

        foreach ($languageColumns as $languageColumn) {
            $this->translateColumn($tableName, $languageColumn);

            if ($this->batchCompleted) {
                return;
            }
        }
    }

    private function translateColumn(string $tableName, string $columnName)
    {
        $rows = DB::table($tableName)
            ->select(
                'id',
                $columnName . '_' . self::BASE_LANGUAGE . ' AS text'
            )
            ->get();

        foreach ($rows as $row) {
            if (!$row->text) {
                continue;
            }

            foreach ($this->locales as $locale) {
                if ($locale == self::BASE_LANGUAGE) {
                    continue;
                }

                // Check if this column already is translated
                $hasTranslation = Translation::where('table', $tableName)
                    ->where('table_id', $row->id)
                    ->where('field', $columnName)
                    ->where('language_code', $locale)
                    ->where('service_id', $this->service->id)
                    ->exists();

                if ($hasTranslation) {
                    continue;
                }

                switch ($this->service->name) {
                    case 'openai':
                        $translateResponse = $this->translationController->translateOpenAI([$row->text], self::BASE_LANGUAGE, $locale);
                        $translation = $translateResponse[0];
                        break;

                    default:
                        $translation = '';
                        break;
                }

                if (!$translation) {
                    continue;
                }

                $this->storeTranslation($tableName, $columnName, $row->id, $translation, $locale, $this->service->id);

                $this->batchCount++;
                if ($this->batchCount >= $this->batchSize) {
                    $this->batchCompleted = true;
                    return;
                }
            }
        }
    }

    protected function storeTranslation(string $table, string $field, int $tableID, string $translatedText, string $languageCode, int $serviceID)
    {
        $translation = Translation::where('table', $table)
            ->where('table_id', $tableID)
            ->where('field', $field)
            ->where('language_code', $languageCode)
            ->where('service_id', $serviceID)
            ->first();

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
}
