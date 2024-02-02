<?php

namespace App\Console\Commands;

use App\Services\VismaNet\VismaDeletionService;
use Illuminate\Console\Command;

class ProcessVismaDeletions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process-visma-deletions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete records locally that have been deleted in Visma.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deletionService = new VismaDeletionService();
        $deletionService->deletePurchaseOrders();
    }
}
