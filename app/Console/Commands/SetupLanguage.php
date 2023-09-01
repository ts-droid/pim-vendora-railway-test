<?php

namespace App\Console\Commands;

use App\Http\Controllers\LanguageController;
use Illuminate\Console\Command;

class SetupLanguage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'language:setup {locale}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add support for a new language in the PIM.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $locale = $this->argument('locale');

        $this->line('Setting up language...');

        $languageController = new LanguageController();
        list($success, $error) = $languageController->setupLanguage($locale);

        if ($success) {
            $this->info('Language successfully set up.');
        }
        else {
            $this->error('Failed to set up language.');
            $this->line('Error: ' . $error);
        }
    }
}
