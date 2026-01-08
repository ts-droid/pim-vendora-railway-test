<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Http\Controllers\ApiKeyController;
use Illuminate\Console\Command;

class GenerateApiKey extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting API key generation.', $this->commandLogContext());

        $apiKeyController = new ApiKeyController();
        $key = $apiKeyController->generate();

        $this->line('');
        $this->info('New API-key generated:');
        $this->line($key);
        $this->line('');

        action_log('Generated new API key.', $this->commandLogContext([
            'generated' => true,
        ]));
    }
}
