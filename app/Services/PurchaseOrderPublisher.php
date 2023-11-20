<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Services\VismaNet\VismaNetPurchaseOrderService;

class PurchaseOrderPublisher
{
    /**
     * Transform a draft order into a published order.
     *
     * @param PurchaseOrder $purchaseOrder
     * @return array
     */
    public function publishOrder(PurchaseOrder $purchaseOrder): array
    {
        if (!$purchaseOrder->is_draft) {
            // Order is not a draft
            return ['success' => false, 'message' => 'Order is not a draft.'];
        }

        // Send the order to Visma.net
        $purchaseOrderService = new VismaNetPurchaseOrderService();
        $purchaseOrderService->createPurchaseOrder($purchaseOrder);

        // Fetch purchase orders to update with data from Visma.net
        $purchaseOrderService->fetchPurchaseOrders();

        return ['success' => true];
    }
}
