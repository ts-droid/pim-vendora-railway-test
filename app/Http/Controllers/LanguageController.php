<?php

namespace App\Http\Controllers;

use Illuminate\Database\Schema\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LanguageController extends Controller
{
    const SUPPORTED_LANGUAGES = ['sv', 'en', 'da', 'no', 'fi'];

    public function localeToTitle(string $locale): string
    {
        switch ($locale) {
            case 'sv':
                return 'Swedish';
            case 'en':
                return 'English';
            case 'da':
                return 'Danish';
            case 'no':
                return 'Norwegian';
            case 'fi':
                return 'Finnish';
            default:
                return '';
        }
    }

    public function setupLanguage(string $locale)
    {
        // Make sure the locale is prepared
        $locale = strtolower($locale);
        if (!in_array($locale, self::SUPPORTED_LANGUAGES)) {
            return [false, 'Unsupported language. You must first add the language to the LanguageController.'];
        }

        // Collect all language columns in the database
        $languageColumns = [];

        $defaultLocale = self::SUPPORTED_LANGUAGES[0];

        $databaseName = env('DB_DATABASE');

        $tables = DB::select('SHOW TABLES');

        foreach ($tables as $table) {
            $tableName = $table->{'Tables_in_' . $databaseName};

            $columns = DB::select('DESCRIBE ' . $tableName);

            foreach ($columns as $column) {
                $columnName = $column->Field;

                if (strpos($columnName, '_' . $defaultLocale) !== false) {

                    if (!isset($languageColumns[$tableName])) {
                        $languageColumns[$tableName] = [];
                    }

                    $languageColumns[$tableName][] = $column;
                }
            }
        }

        // Add the new language columns
        foreach ($languageColumns as $table => $columns) {
            foreach ($columns as $column) {
                $baseColumnName = str_replace('_' . $defaultLocale, '', $column->Field);

                $this->addLanguageColumn(
                    $table,
                    $baseColumnName,
                    $locale,
                    $column->Type,
                    ($column->Null == 'YES'),
                    $column->Default
                );
            }
        }

        return [true, ''];
    }

    private function addLanguageColumn(string $tableName, string $columnName, string $locale, string $columnType, bool $nullable, mixed $default)
    {
        $orgColumnName = $columnName . '_' . self::SUPPORTED_LANGUAGES[0];
        $newColumnName = $columnName . '_' . $locale;

        $parameters = [];

        $columnType = strtolower($columnType);

        if (str_contains($columnType, 'varchar')) {
            $columnType = 'string';
            $parameters['length'] = Builder::$defaultStringLength;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnType, $newColumnName, $orgColumnName, $parameters, $nullable, $default) {
            $table->addColumn($columnType, $newColumnName, $parameters)->nullable($nullable)->default($default)->after($orgColumnName);
        });
    }
}
