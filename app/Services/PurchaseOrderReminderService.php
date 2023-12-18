<?php

namespace App\Services;

use App\Jobs\SendPurchaseOrderReminder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;

class PurchaseOrderReminderService
{
    /**
     * Send reminders to the suppliers for uncompleted purchase order lines
     *
     * @param array $purchaseOrderLineIDs
     * @return void
     */
    public function remind(array $purchaseOrderLineIDs): void
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

            // Dispatch the reminder to the queue
            dispatch(new SendPurchaseOrderReminder($purchaseOrder, $orderLines));
        }
    }
}
