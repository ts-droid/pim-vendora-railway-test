<?php

namespace App\Services\VismaNet;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Shipment;

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
            ->pluck('id');

        if ($orderIDs->count() > 0) {
            PurchaseOrder::whereIn('id', $orderIDs)->delete();

            PurchaseOrderLine::whereIn('purchase_order_id', $orderIDs)->delete();
        }
    }

    public function deleteShipments(): void
    {
        $shipments = Shipment::where('status', '=', 'Open')->get();

        if (!$shipments) {
            return;
        }

        $vismaNetShipmentService = new VismaNetShipmentService();

        foreach ($shipments as $shipment) {
            $vismaNetShipmentService->deleteIfDeleted($shipment);
        }
    }
}
