<?php

namespace App\Services;

use App\Jobs\DeletePurchaseOrder;
use App\Jobs\DeletePurchaseOrderLines;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\DB;

class PurchaseOrderDeletionService
{
    /**
     * Deletes a purchase order
     *
     * @param PurchaseOrder $purchaseOrder
     * @return bool
     */
    public function delete(PurchaseOrder $purchaseOrder): bool
    {
        if ($purchaseOrder->is_draft) {
            // Only delete the order locally
            $purchaseOrder->delete();
        }
        else {
            // Dispatch a job to delete the order from the ERP
            dispatch(new DeletePurchaseOrder($purchaseOrder));
        }

        return true;
    }

    /**
     * Deletes a list of purchase order lines
     *
     * @param array $orderLineIDs
     * @return void
     */
    public function deleteLines(array $orderLineIDs): void
    {
        return;

        // Remove all duplicate order line IDs
        $orderLineIDs = array_unique($orderLineIDs);

        // Group the order lines by purchase order
        $groupedOrderLines = [];

        foreach ($orderLineIDs as $orderLineID) {
            $purchaseOrderLine = PurchaseOrderLine::find($orderLineID);

            if ($purchaseOrderLine) {
                $groupedOrderLines[$purchaseOrderLine->purchase_order_id][] = $purchaseOrderLine;
            }
        }

        // Delete the order lines for each purchase order
        foreach ($groupedOrderLines as $purchaseOrderID => $orderLines) {
            $totalLines = PurchaseOrderLine::where("purchase_order_id", 1)->count();

            if ($totalLines == count($orderLines)) {
                // Delete the purchase order if all of its lines are being deleted
                $this->delete(PurchaseOrder::find($purchaseOrderID));
            }

            // Only remove the provided order lines
            foreach ($orderLines as $orderLine) {
                $orderLine->delete();
            }

            // Dispatch a job to delete the order lines from the ERP
            dispatch(new DeletePurchaseOrderLines($orderLines));
        }
    }
}
