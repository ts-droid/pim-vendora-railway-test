<?php

namespace App\Console\Commands;

use App\Http\Controllers\ApiKeyController;
use Illuminate\Console\Command;

class GenerateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate-key';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates a new API key';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiKeyController = new ApiKeyController();
        $key = $apiKeyController->generate();

        $this->line('');
        $this->info('New API-key generated:');
        $this->line($key);
        $this->line('');
    }
}
