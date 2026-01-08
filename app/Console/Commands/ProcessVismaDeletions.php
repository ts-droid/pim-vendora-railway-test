<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\VismaNet\VismaDeletionService;
use Illuminate\Console\Command;

class ProcessVismaDeletions extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting Visma deletion processing.', $this->commandLogContext());

        $deletionService = new VismaDeletionService();
        $deleted = $deletionService->deletePurchaseOrders();

        action_log('Finished Visma deletion processing.', $this->commandLogContext([
            'deleted_purchase_orders' => $deleted,
        ]));
    }
}
