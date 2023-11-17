<?php

namespace App\Services;

use App\Models\PurchaseOrder;

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
        if (!$purchaseOrder->is_draft) {
            // Do not allow deletion of non-draft purchase orders
            return false;
        }

        $purchaseOrder->delete();

        return true;
    }
}
