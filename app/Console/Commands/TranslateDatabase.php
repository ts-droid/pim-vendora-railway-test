<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\LanguageFieldTranslator;
use Illuminate\Console\Command;

class TranslateDatabase extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translate-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translates all models in the database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting database translation.', $this->commandLogContext());

        $languageFieldTranslator = new LanguageFieldTranslator(5000);
        // $languageFieldTranslator->translateDatabase();

        action_log('Finished database translation.', $this->commandLogContext());
    }
}
