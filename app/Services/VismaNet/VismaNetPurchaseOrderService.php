<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\VismaNetController;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\Http;

class VismaNetPurchaseOrderService extends VismaNetApiService
{
    /**
     * Creates a purchase order in Visma.net based on a local purchase order
     *
     * @param PurchaseOrder $purchaseOrder
     * @param bool $hold
     * @return array|true[]
     */
    public function createPurchaseOrder(PurchaseOrder $purchaseOrder, bool $hold = false): array
    {
        $lines = [];

        $orderLines = $purchaseOrder->lines;

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
                'orderQty' => ['value' => $orderLine->quantity],
                'unitCost' => ['value' => $orderLine->unit_cost],
                'amount' => ['value' => $orderLine->amount],
                'promised' => ['value' => $orderLine->promised_date] // Add 5 days to the promised date
            ];
        }

        $data = [
            'orderNumber' => ['value' => $purchaseOrder->order_number],
            'orderType' => ['value' => 'RegularOrder'],
            'supplier' => ['value' => $purchaseOrder->supplier_number],
            'currency' => ['value' => $purchaseOrder->currency],
            'promisedOn' => ['value' => $purchaseOrder->promised_date], // Add 5 days to the promised date
            'dontEmail' => ['value' => true],
            'hold' => ['value' => $hold],
            'lines' => $lines,
        ];


        $response = $this->callAPI('POST', '/v1/purchaseorder', $data);

        $orderNumber = $this->getIdFromLocation($response['headers']['Location'][0] ?? '');

        if (!$orderNumber) {
            $logID = log_data(json_encode($response));
            return ['success' => false, 'message' => 'Failed to create purchase order. (LOG ID: ' . $logID . ')'];
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

    /**
     * Updates a purchase order in visma based on the local purchase order
     *
     * @param PurchaseOrder $purchaseOrder
     * @return array|true[]
     */
    public function updatePurchaseOrder(PurchaseOrder $purchaseOrder, ?bool $onHold = null): array
    {
        // Fetch purchase order from Visma.net so that we can detect changes
        $response = $this->callAPI('GET', '/v1/purchaseorder/' . $purchaseOrder->order_number);
        $remoteOrder = $response['response'] ?? [];

        if (empty($remoteOrder['orderNbr'])) {
            return ['success' => false, 'message' => 'Remote order could not be found.'];
        }

        $lines = [];

        foreach ($remoteOrder['lines'] as $remoteLine) {
            $localLine = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
                ->where('article_number', $remoteLine['inventory']['number'])
                ->first();

            if (!$localLine) {
                // The line does not exist locally, so remove it
                $lines[] = [
                    'operation' => 'Delete',
                    'lineNumber' => ['value' => $remoteLine['lineNbr']],
                ];
            }
            else {
                // Update the line
                $lines[] = [
                    'operation' => 'Update',
                    'lineNumber' => ['value' => $remoteLine['lineNbr']],
                    'inventory' => ['value' => $localLine->article_number],
                    'lineDescription' => ['value' => $localLine->description],
                    'orderQty' => ['value' => $localLine->quantity],
                    'unitCost' => ['value' => $localLine->unit_cost],
                    'amount' => ['value' => $localLine->amount],
                    'promised' => ['value' => $localLine->promised_date]
                ];

                $localLine->update([
                    'line_key' => $remoteLine['lineNbr']
                ]);
            }
        }

        $data = [
            'currency' => ['value' => $purchaseOrder->currency],
            'promisedOn' => ['value' => $purchaseOrder->promised_date],
            'lines' => $lines
        ];

        if ($onHold !== null) {
            $data['hold'] = ['value' => $onHold];
        }

        $response = $this->callAPI('PUT', '/v1/purchaseorder/' . $purchaseOrder->order_number, $data);

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => ($response['response']['message'] ?? 'Unknown error'),
                'meta' => $data
            ];
        }

        return ['success' => true];
    }

    public function fetchPurchaseOrders(string $updatedAfter = '', string $orderNumber = '')
    {
        // TODO: Move the called function this this service class
        $vismaNetController = new VismaNetController();
        $vismaNetController->fetchPurchaseOrders($updatedAfter, $orderNumber);
    }
}
