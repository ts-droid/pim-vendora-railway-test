<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\PurchaseOrderEmailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendPurchaseOrderReminders extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase-order:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all reminders related to purchase orders.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting purchase order reminder evaluation.', $this->commandLogContext());

        $emailer = new PurchaseOrderEmailer();

        $this->remindConfirm($emailer);
        $this->remindShipping($emailer);
        $this->remindInvoice($emailer);
        $this->remindETD($emailer);

        action_log('Finished purchase order reminder evaluation.', $this->commandLogContext());
    }

    public function remindConfirm(PurchaseOrderEmailer $emailer): void
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

            $emailer->sendConfirmReminder($purchaseOrder);
        }
    }

    public function remindShipping(PurchaseOrderEmailer $emailer): void
    {
        $purchaseOrderIDs = DB::table('purchase_order_lines')
            ->select('purchase_order_id')
            ->where('is_completed', 0)
            ->where('is_shipped', 1)
            ->where(function($q) {
                $q->where('tracking_number', '=', '')
                    ->orWhereNull('tracking_number');
            })
            ->distinct()
            ->pluck('purchase_order_id');

        if (!$purchaseOrderIDs->count()) {
            return;
        }

        $purchaseOrders = PurchaseOrder::whereIn('id', $purchaseOrderIDs)->get();

        foreach ($purchaseOrders as $purchaseOrder) {
            if (!has_hours_passed($purchaseOrder->shipping_reminder_sent_at, 24)) {
                continue;
            }

            $emailer->sendShippingReminder($purchaseOrder);
        }
    }

    public function remindInvoice(PurchaseOrderEmailer $emailer): void
    {
        $purchaseOrders = PurchaseOrder::where('status_confirmed_by_supplier', 1)
            ->get();

        if (!$purchaseOrders->count()) {
            return;
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            if ($purchaseOrder && !has_hours_passed($purchaseOrder->invoice_reminder_sent_at, 24)) {
                continue;
            }

            foreach ($purchaseOrder->lines as $orderLine) {
                if ($orderLine->is_shipped && !$orderLine->invoice_id) {
                    // This line is missing an invoice, send reminder
                    $emailer->sendInvoiceReminder($purchaseOrder);
                    break;
                }
            }
        }
    }

    public function remindETD(PurchaseOrderEmailer $emailer): void
    {
        $purchaseOrderLines = PurchaseOrderLine::where('promised_date', '<=', date('Y-m-d'))
            ->where('promised_date', '!=', '')
            ->whereNotNull('promised_date')
            ->where('is_locked', 1)
            ->where('is_shipped', 0)
            ->where('is_completed', 0)
            ->get();

        if (!$purchaseOrderLines->count()) {
            return;
        }

        $groupedReminders = [];

        foreach ($purchaseOrderLines as $purchaseOrderLine) {
            if (!has_hours_passed($purchaseOrderLine->reminder_sent_at, 24)) {
                continue;
            }

            if (!isset($groupedReminders[$purchaseOrderLine->purchase_order_id])) {
                $groupedReminders[$purchaseOrderLine->purchase_order_id] = [];
            }

            $groupedReminders[$purchaseOrderLine->purchase_order_id][] = $purchaseOrderLine;
        }

        if (count($groupedReminders) > 0) {
            foreach ($groupedReminders as $purchaseOrderId => $lines) {
                $emailer->sendETDReminder($purchaseOrderId, $lines);
            }
        }
    }
}
