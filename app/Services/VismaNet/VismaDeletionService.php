<?php

namespace App\Services\VismaNet;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;

class VismaDeletionService extends VismaNetApiService
{
    public function deletePurchaseOrders(): void
    {
        $purchaseOrders = $this->getPagedResult('/v1/purchaseorderbasic');

        $orderNumbers = array_column($purchaseOrders, 'orderNbr');

        if (!$orderNumbers) {
            // The call to the Visma.net API returned no purchase orders.
            // Do not continue with the deletion process.
            return;
        }

        // Delete all purchase orders not found in the Visma.net API response.
        $orderIDs = PurchaseOrder::whereNotIn('order_number', $orderNumbers)
            ->where('status', '!=', 'Draft')
            ->where('date', '>=', '2023-01-01')
            ->pluck('id')
            ->get();

        PurchaseOrder::whereIn('id', $orderIDs)->delete();

        PurchaseOrderLine::whereIn('purchase_order_id', $orderIDs)->delete();
    }
}
