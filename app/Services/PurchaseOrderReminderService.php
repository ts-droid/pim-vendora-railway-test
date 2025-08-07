<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Jobs\SendPurchaseOrderReminder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;

class PurchaseOrderReminderService
{
    public function remindETA(): void
    {
        // Load all open order lines
        $orderLines = PurchaseOrderLine::select('purchase_order_lines.*', 'suppliers.supplier_contact_email as email')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.external_id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.status', '=', 'Open')
            ->where('purchase_order_lines.quantity', '>', 'purchase_order_lines.quantity_received')
            ->where('purchase_order_lines.is_completed', '=', 0)
            ->where(function ($query) {
                $query->where('purchase_order_lines.promised_date', '<', date('Y-m-d'))
                    ->orWhereNull('purchase_order_lines.promised_date');
            })
            ->where('purchase_orders.should_delete', '=', 0)
            ->get();

        if (!$orderLines) {
            return;
        }

        // Extract email recipients
        $emailRecipients = [];

        foreach ($orderLines as $orderLine) {
            $emailRecipients[$orderLine->purchase_order_id] = $orderLine->email;
        }

        $this->remindETALines($orderLines, $emailRecipients);
    }

    public function remindETARequest(array $purchaseOrderLineIDs, array $emailRecipients = []): void
    {
        $orderLines = PurchaseOrderLine::whereIn('id', $purchaseOrderLineIDs)->get();

        if (!$orderLines) {
            return;
        }

        $this->remindETALines($orderLines, $emailRecipients);
    }

    public function remindETALines($orderLines, array $emailRecipients = []): void
    {
        $orderLinesByOrder = [];

        foreach ($orderLines as $orderLine) {
            // Update timestamp for reminder sent
            $orderLine->update(['reminder_sent_at' => date('Y-m-d H:i:s')]);

            PurchaseOrder::where('id', $orderLine->purchase_order_id)
                ->update(['reminder_sent_at' => date('Y-m-d H:i:s')]);

            // Group order lines by order
            if (!isset($orderLinesByOrder[$orderLine->purchase_order_id])) {
                $orderLinesByOrder[$orderLine->purchase_order_id] = collect();
            }

            $orderLinesByOrder[$orderLine->purchase_order_id]->push($orderLine);
        }

        foreach ($orderLinesByOrder as $orderID => $orderLines) {
            $purchaseOrder = PurchaseOrder::find($orderID);
            if (!$purchaseOrder) {
                continue;
            }

            dispatch(new SendPurchaseOrderReminder($purchaseOrder, $orderLines, $emailRecipients[$orderID] ?? ''));
        }
    }


    /**
     * Send a reminder for all draft purchase orders that have not been reminded
     *
     * @return void
     */
    public function remindDrafts(): void
    {
        $remindInterval = (int) ConfigController::getConfig('purchase_system_draft_reminder_interval', 2);

        $purchaseOrders = PurchaseOrder::where('is_po_system', 1)
            ->whereNull('published_at')
            ->where('is_sent', 1)
            ->where('is_confirmed', 1)
            ->where('date', '<', date('Y-m-d', strtotime('-' . $remindInterval . ' days')))
            ->where(function($query) use ($remindInterval) {
                $query->where('draft_reminder_sent_at', '<', date('Y-m-d H:i:s', strtotime('-' . $remindInterval . ' day')))
                    ->orWhereNull('draft_reminder_sent_at');
            })
            ->get();

        if (!$purchaseOrders) {
            return;
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            $this->remindPurchaseOrderDraft($purchaseOrder);
        }
    }

    /**
     * Send a reminder for a draft purchase order
     *
     * @param PurchaseOrder $purchaseOrder
     * @return void
     */
    public function remindPurchaseOrderDraft(PurchaseOrder $purchaseOrder): void
    {
        // Make sure the order is not yet published
        if ($purchaseOrder->published_at) {
            return;
        }

        // Send the reminder
        $mailer = new PurchaseOrderEmailer();
        list($success, $message) = $mailer->send($purchaseOrder, true);

        if (!$success) {
            log_data('Failed to send reminder for purchase order #' . $purchaseOrder->id . ': ' . $message);
            return;
        }

        // Update timestamp for reminder sent
        $purchaseOrder->update([
            'draft_num_reminders_sent' => 1 + intval($purchaseOrder->draft_num_reminders_sent),
            'draft_reminder_sent_at' => date('Y-m-d H:i:s')
        ]);
    }
}
