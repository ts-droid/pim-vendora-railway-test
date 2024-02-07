<?php

namespace App\Services\VismaNet;

use App\Models\PurchaseOrder;

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
        PurchaseOrder::whereNotIn('order_number', $orderNumbers)
            ->where('status', '!=', 'Draft')
            ->delete();
    }
}
