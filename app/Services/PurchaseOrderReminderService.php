<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Jobs\SendPurchaseOrderReminder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;

class PurchaseOrderReminderService
{
    /**
     * Send reminders to the suppliers for uncompleted purchase order lines
     *
     * @param array $purchaseOrderLineIDs
     * @param array $emailRecipients
     * @return void
     */
    public function remind(array $purchaseOrderLineIDs, array $emailRecipients = array()): void
    {
        $orderLines = PurchaseOrderLine::whereIn('id', $purchaseOrderLineIDs)->get();

        if (!$orderLines) {
            return;
        }

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

            $emailRecipient = $emailRecipients[$orderID] ?? null;

            // Dispatch the reminder to the queue
            dispatch(new SendPurchaseOrderReminder($purchaseOrder, $orderLines, $emailRecipient));
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

        $purchaseOrders = PurchaseOrder::where('status', 'Draft')
            ->where('is_sent', 1)
            ->where('is_confirmed', 1)
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-' . $remindInterval . ' days')))
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
