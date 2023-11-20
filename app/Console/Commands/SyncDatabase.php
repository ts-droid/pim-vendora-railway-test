<?php

namespace App\Console\Commands;

use App\Services\DatabaseSyncService;
use Illuminate\Console\Command;

class SyncDatabase extends Command
{
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
        $syncService = new DatabaseSyncService();
        $syncService->sync();
    }
}
