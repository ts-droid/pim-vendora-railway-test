<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\ApiLogger;
use Illuminate\Console\Command;

class CleanApiLogger extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-logger:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean the api logger table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting API logger cleanup.', $this->commandLogContext());

        ApiLogger::cleanup();
        $this->info('Api logger cleaned');

        action_log('Finished API logger cleanup.', $this->commandLogContext());
    }
}
