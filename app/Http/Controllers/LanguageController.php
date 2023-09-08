<?php

namespace App\Http\Controllers;

use App\Jobs\SetupLanguage;
use App\Models\Language;
use Illuminate\Database\Schema\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LanguageController extends Controller
{
    // TODO: Replace the usage of this
    const SUPPORTED_LANGUAGES = ['sv', 'en', 'da', 'no', 'fi'];







    const DEFAULT_LANGUAGE = 'sv';

    /**
     * Returns all languages
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllLanguages()
    {
        return Language::all();
    }

    /**
     * Returns all active languages
     *
     * @return mixed
     */
    public function getActiveLanguages()
    {
        return Language::where('is_active', 1)->get();
    }

    /**
     * Returns a language based on the language code
     *
     * @param string $languageCode
     * @return mixed
     */
    public function getLanguageByCode(string $languageCode)
    {
        return Language::where('language_code', $languageCode)->first();
    }

    /**
     * Activates a language
     *
     * @param Language $language
     * @return Language
     */
    public function activateLanguage(Language $language)
    {
        return $language;
    }

    /**
     * Deactivates a language
     *
     * @param Language $language
     * @return Language
     */
    public function deactivateLanguage(Language $language)
    {
        return $language;
    }

    /**
     * Adds support for a new language
     *
     * @param string $languageCode
     * @param string $title
     * @param string $titleLocal
     * @param string $defaultCurrency
     * @return Language|bool
     */
    public function createLanguage(string $languageCode, string $title, string $titleLocal, string $defaultCurrency): Language|bool
    {
        $languageCodeExists = Language::where('language_code', $languageCode)->exists();

        if ($languageCodeExists) {
            return false;
        }

        $language = Language::create([
            'language_code' => $languageCode,
            'title' => $title,
            'title_local' => $titleLocal,
            'default_currency' => $defaultCurrency,
            'is_active' => 0,
        ]);

        SetupLanguage::dispatch($language);

        return $language;
    }

    /**
     * Setup language columns in the database.
     *
     * @param string $languageCode
     * @return void
     */
    public function setupLanguageColumns(string $languageCode)
    {
        // Collect all language columns in the database
        $languageColumns = [];

        $databaseName = env('DB_DATABASE');

        $tables = DB::select('SHOW TABLES');

        foreach ($tables as $table) {
            $tableName = $table->{'Tables_in_' . $databaseName};

            $columns = DB::select('DESCRIBE ' . $tableName);

            foreach ($columns as $column) {
                $columnName = $column->Field;

                if (str_contains($columnName, '_' . self::DEFAULT_LANGUAGE)) {

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
                $baseColumnName = str_replace('_' . self::DEFAULT_LANGUAGE, '', $column->Field);

                $this->addLanguageColumn(
                    $table,
                    $baseColumnName,
                    $languageCode,
                    $column->Type,
                    ($column->Null == 'YES'),
                    $column->Default
                );
            }
        }
    }

    /**
     * Adds a language column to a table if it doesn't already exist.
     *
     * @param string $tableName
     * @param string $columnName
     * @param string $locale
     * @param string $columnType
     * @param bool $nullable
     * @param mixed $default
     * @return void
     */
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

        if (Schema::hasColumn($tableName, $newColumnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnType, $newColumnName, $orgColumnName, $parameters, $nullable, $default) {
            $table->addColumn($columnType, $newColumnName, $parameters)->nullable($nullable)->default($default)->after($orgColumnName);
        });
    }



















    // TODO: Replace the usage of this
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
}
