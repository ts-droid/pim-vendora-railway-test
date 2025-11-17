<?php

namespace App\Console\Commands;

use App\Mail\NotifyPurchaseOrderExceptions;
use App\Models\PurchaseOrderException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Mail;


class HandlePurchaseOrderExceptions extends Command implements ShouldBeUnique
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase-order-exceptions:handle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle all purchase order exceptions.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $unhandledExceptions = PurchaseOrderException::whereNull('handled_at')->get();

        if (!$unhandledExceptions->count()) return;

        $groupedExceptions = [];
        foreach ($unhandledExceptions as $exception) {
            $supplierID = $exception->purchaseOrderShipment->purchaseOrder->supplier_id ?? 0;
            if (!$supplierID) continue;

            if (!isset($groupedExceptions[$supplierID])) {
                $groupedExceptions[$supplierID] = [
                    'email' => $exception->purchaseOrderShipment->purchaseOrder->email,
                    'purchase_order_shipment' => $exception->purchaseOrderShipment,
                    'exceptions' => []
                ];
            }

            $groupedExceptions[$supplierID]['exceptions'][] = $exception;
        }

        foreach ($groupedExceptions as $supplierID => $data) {
            $email = $data['email'];
            $purchaseOrderShipment = $data['purchase_order_shipment'];
            $exceptions = $data['exceptions'];

            // TODO: Queue notification
            $recipients = [
                'anton@scriptsector.se',
                // $email,
                // 'purchasing@vendora.se',
                // 'logistics@vendora.se'
            ];

            Mail::to($recipients)->queue(new NotifyPurchaseOrderExceptions($purchaseOrderShipment, $exceptions));

            foreach ($exceptions as $exception) {
                $exception->update(['handled_at' => now()]);
            }
        }
    }
}
