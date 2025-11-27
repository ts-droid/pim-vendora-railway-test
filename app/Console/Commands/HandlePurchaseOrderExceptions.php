<?php

namespace App\Console\Commands;

use App\Enums\LaravelQueues;
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
            if (!isset($groupedExceptions[$exception->purchase_order_shipment_id])) {
                $groupedExceptions[$exception->purchase_order_shipment_id] = [
                    'email' => $exception->purchaseOrderShipment->purchaseOrder->email,
                    'purchase_order_shipment' => $exception->purchaseOrderShipment,
                    'exceptions' => []
                ];
            }

            $groupedExceptions[$exception->purchase_order_shipment_id]['exceptions'][] = $exception;
        }

        foreach ($groupedExceptions as $data) {
            $email = $data['email'];
            $purchaseOrderShipment = $data['purchase_order_shipment'];
            $exceptions = $data['exceptions'];

            $recipients = [
                'purchasing@vendora.se',
                'logistics@vendora.se',
                'anton@vendora.se'
            ];

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // $recipients[] = $email;
            }

            Mail::to($recipients)->queue((new NotifyPurchaseOrderExceptions($purchaseOrderShipment, $exceptions))->onQueue(LaravelQueues::MAIL->value));

            foreach ($exceptions as $exception) {
                $exception->update(['handled_at' => now()]);
            }
        }
    }
}
