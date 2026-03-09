<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrderLine;
use Illuminate\Console\Command;

class PostponeShipments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shipments:postpone-eta';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Postpone delayed ETA dates on purchase order lines.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $oldPurchaseOrderLines = PurchaseOrderLine::whereDate('promised_date', '<', date('Y-m-d'))
            ->whereNotNull('promised_date')
            ->where('promised_date', '!=', '')
            ->whereColumn('quantity', '>', 'quantity_received')
            ->whereHas('purchaseOrder', function ($query) {
                $query->where('is_po_system', 1);
            })
            ->get();

        if ($oldPurchaseOrderLines->count() === 0) {
            return;
        }

        foreach ($oldPurchaseOrderLines as $oldPurchaseOrderLine) {
            $oldPurchaseOrderLine->update(['promised_date' => date('Y-m-d', strtotime('+5 days'))]);
        }
    }
}
