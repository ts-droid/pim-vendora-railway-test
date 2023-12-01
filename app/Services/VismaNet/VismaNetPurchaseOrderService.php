<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\VismaNetController;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Http;

class VismaNetPurchaseOrderService extends VismaNetApiService
{
    /**
     * Creates a purchase order in Visma.net based on a local purchase order
     *
     * @param PurchaseOrder $purchaseOrder
     * @return array|true[]
     */
    public function createPurchaseOrder(PurchaseOrder $purchaseOrder): array
    {
        $lines = [];

        $orderLines = $purchaseOrder->lines();

        if (!$orderLines->count()) {
            return ['success' => false, 'message' => 'Purchase order has no lines.'];
        }

        foreach ($orderLines as $orderLine) {
            $lines[] = [
                'operation' => 'Insert',
                'lineNumber' => ['value' => $orderLine->line_key],
                'inventory' => ['value' => $orderLine->article_number],
                'lineType' => ['value' => 'GoodsForInventory'],
                'lineDescription' => ['value' => $orderLine->description],
                'orderQty' => ['values' => $orderLine->quantity],
                'unitCost' => ['value' => $orderLine->unit_cost],
                'amount' => ['value' => $orderLine->amount],
            ];
        }

        $data = [
            'orderType' => ['value' => 'RegularOrder'],
            'supplier' => ['value' => $purchaseOrder->supplier_number],
            'currency' => ['value' => $purchaseOrder->currency],
            'lines' => $lines,
        ];


        $response = $this->callAPI('POST', '/v1/purchaseorder', $data);

        $orderNumber = $this->getIdFromLocation($response['headers']['location'] ?? '');

        if (!$orderNumber) {
            return ['success' => false, 'message' => 'Failed to create purchase order.'];
        }

        // Update the purchase order with the Visma.net order ID
        $purchaseOrder->update([
            'order_number' => $orderNumber,
            'is_draft' => 0,
        ]);

        return [
            'success' => true,
        ];
    }

    public function fetchPurchaseOrders(string $updatedAfter = '')
    {
        // TODO: Move the called function this this service class
        $vismaNetController = new VismaNetController();
        $vismaNetController->fetchPurchaseOrders($updatedAfter);
    }
}
