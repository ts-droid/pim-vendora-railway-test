<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Services\VismaNet\VismaDeletionService;
use App\Services\VismaNet\VismaNetShipmentService;
use Illuminate\Console\Command;

class DeleteVismaShipments extends Command
{
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
        $vismaDeletionService = new VismaDeletionService();
        $vismaDeletionService->deleteShipments();
    }
}
