<?php

namespace App\Console\Commands;

use App\Services\TranslationServiceManager;
use Illuminate\Console\Command;

class WorkTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translation:work';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Long lived command that translate all translation services.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        return;

        $manager = new TranslationServiceManager();

        while(true) {
            $fulfilled = $manager->executeBatch(30);

            sleep($fulfilled ? 5 : 120);
        }
    }
}
