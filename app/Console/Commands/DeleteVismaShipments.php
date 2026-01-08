<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\VismaNet\VismaDeletionService;
use App\Services\VismaNet\VismaNetShipmentService;
use Illuminate\Console\Command;

class DeleteVismaShipments extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visma:delete-shipments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete shipments locally that have been deleted in visma.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting Visma shipment deletion sync.', $this->commandLogContext());

        $vismaDeletionService = new VismaDeletionService();
        $deleted = $vismaDeletionService->deleteShipments();

        action_log('Finished Visma shipment deletion sync.', $this->commandLogContext([
            'deleted_shipments' => $deleted,
        ]));
    }
}
