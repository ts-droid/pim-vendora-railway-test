<?php

namespace App\Console\Commands;

use App\Services\LanguageFieldTranslator;
use Illuminate\Console\Command;

class TranslateDatabase extends Command
{
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
        $languageFieldTranslator = new LanguageFieldTranslator(25);
        $languageFieldTranslator->translateDatabase();
    }
}
