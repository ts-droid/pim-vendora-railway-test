<?php

namespace App\Services;

use App\Http\Controllers\LanguageController;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;

class TranslationServiceManager
{
    const BASE_LANGUAGE = 'en';

    private array $locales;

    private int $batchSize = 10;
    private int $batchCount = 0;

    function __construct(int $batchSize = 10)
    {
        $this->batchSize = $batchSize;

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

        dd($rows);

        echo $tableName . ' . ' . $columnName;
        die();
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
