<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\DatabaseSyncService;
use Illuminate\Console\Command;

class SyncDatabase extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'database:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync data from prod to local.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting database sync.', $this->commandLogContext());

        $syncService = new DatabaseSyncService();
        $syncService->sync();

        action_log('Finished database sync.', $this->commandLogContext());
    }
}
