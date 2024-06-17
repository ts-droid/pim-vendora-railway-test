<?php

namespace App\Console\Commands;

use App\Models\TranslationService;
use App\Services\TranslationServiceManager;
use Illuminate\Console\Command;

class TranslateDatabaseVariants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translate-database-variants {serviceID} {batchSize?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate alternative translations for all models in the database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $serviceID = (int) $this->argument('serviceID');
        $batchSize = (int) $this->argument('batchSize') ?: 10;

        if ($batchSize <= 0) {
            $this->error('Batch size must be greater than 0.');
            return;
        }

        $service = TranslationService::find($serviceID);
        if (!$service) {
            $this->error('Service not found.');
            return;
        }

        $this->info('Engine: ' . $service->name);
        $this->info('Batch size: ' . $batchSize);

        $manager = new TranslationServiceManager($service, $batchSize);
        $manager->translateDatabase();
    }
}
