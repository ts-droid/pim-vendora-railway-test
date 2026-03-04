<?php

namespace App\Console\Commands;

use App\Enums\LaravelQueues;
use App\Http\Controllers\LanguageController;
use App\Jobs\TranslateColumn;
use App\Services\LanguageFieldTranslator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TranslateLanguage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translate-language {--from=} {--to=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-run all translations for a specific language';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $from = $this->option('from');
        $to = $this->option('to');

        $languages = (new LanguageController())->getAllLanguages();
        $languageCodes = $languages->pluck('language_code')->toArray();

        if (!in_array($from, $languageCodes) || !in_array($to, $languageCodes)) {
            $this->error('Invalid language code. Available codes: ' . implode(', ', $languageCodes));
            return;
        }

        $languageTranslator = new LanguageFieldTranslator();

        // Fetch all models
        $path = app_path('/Models');
        $models = $languageTranslator->getModels($path);

        $queueCount = 0;

        foreach ($models as $model) {
            $model = 'App\Models\\' . $model;

            $tableName = (new $model())->getTable();

            $languageAttributes = $languageTranslator->getLanguageAttributes($model);

            if (count($languageAttributes) === 0) continue;

            try {
                $rowIDs = DB::table($tableName)->select('id')->pluck('id')->toArray();
            } catch (\Throwable $e) {
                $rowIDs = [];
            }

            foreach ($rowIDs as $rowID) {
                foreach ($languageAttributes as $languageAttribute) {
                    TranslateColumn::dispatch(
                        $tableName,
                        (int) $rowID,
                        $languageAttribute . '_' . $from,
                        $languageAttribute . '_' . $to,
                    )->onQueue(LaravelQueues::GENERATION->value);

                    $queueCount++;
                }
            }
        }

        $this->info('Dispatched ' . $queueCount . ' translation jobs to the queue.');
    }
}
