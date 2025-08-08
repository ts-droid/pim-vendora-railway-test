<?php

namespace App\Jobs;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPurchaseOrderReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->remindInvoice();
        $this->remindShippingDates();
        $this->remindInvoice();
    }

    private function remindConfirm(): void
    {
        $purchaseOrders = PurchaseOrder::where('status_sent_to_supplier', 1)
            ->where('status_confirmed_by_supplier', 0)
            ->get();

        if (!$purchaseOrders->count()) {
            return;
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            if (!has_hours_passed($purchaseOrder->confirm_reminder_sent_at, 48)) {
                continue;
            }

            // TODO: Send reminder to supplier
        }
    }

    private function remindShippingDates(): void
    {
        $purchaseOrders = PurchaseOrder::where('status_confirmed_by_supplier', 1)
            ->where('status_received', 0)
            ->get();

        foreach ($purchaseOrders as $purchaseOrder) {
            if (!has_hours_passed($purchaseOrder->shipping_reminder_sent_at, 48)) {
                continue;
            }

            $sendReminder = false;

            foreach ($purchaseOrder->lines as $orderLine) {
                if (!$orderLine->promised_date) {
                    // Missing one or more shipping dates
                    $sendReminder = true;
                    break;
                }

                if ($orderLine->tracking_number) {
                    // Tracking number is present, no need to remind
                    continue;
                }

                $deliveryTime = ($purchaseOrder->supplier->general_delivery_time ?? 0);

                $promisedDate = strtotime($orderLine->promised_date);
                $shippingDate = $promisedDate - (($deliveryTime + 1) * 86400);

                if ($shippingDate < time()) {
                    // At least one shipping date is in the past and missing tracking number
                    $sendReminder = true;
                    break;
                }
            }

            if (!$sendReminder) {
                continue;
            }

            // TODO: Send reminder to supplier
        }
    }

    private function remindInvoice(): void
    {
        $purchaseOrders = PurchaseOrder::where('status_confirmed_by_supplier', 1)
            ->get();

        foreach ($purchaseOrders as $purchaseOrder) {
            if ($purchaseOrder->invoice_reminder_sent_at && !has_hours_passed($purchaseOrder->invoice_reminder_sent_at, 48)) {
                continue;
            }

            $sendReminder = false;

            foreach ($purchaseOrder->lines as $orderLine) {
                if (!$orderLine->is_completed) {
                    continue;
                }

                if ($orderLine->invoice_id) {
                    continue;
                }

                $sendReminder = true;
            }

            if (!$sendReminder) {
                continue;
            }

            // TODO: Send reminder to supplier
        }
    }
}
