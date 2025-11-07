<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\VismaNetController;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use Illuminate\Support\Facades\Http;
use DateTime;
use Illuminate\Support\Facades\Log;

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
                'promised' => ['value' => $orderLine->promised_date], // Add 5 days to the promised date
                'warehouse' => ['value' => ($purchaseOrder->is_direct ? self::WAREHOUSE_DIRECT_ID : self::WAREHOUSE_ID)],
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
            $logID = log_data(json_encode(['data' => $data, 'response' => $response]));
            $vismaError = ($response['response']['message'] ?? 'Unknown error');
            return ['success' => false, 'message' => 'Failed to create purchase order. (LOG ID: ' . $logID . ') (Visma error: ' . $vismaError . ')'];
        }

        // Update the purchase order with the Visma.net order ID
        $purchaseOrder->update([
            'order_number' => $orderNumber,
            'status_sent_external' => 1,
            'is_draft' => 0,
        ]);

        // Update the line keys
        $response = $this->callAPI('GET', '/v1/purchaseorder/' . $orderNumber);
        $remoteOrder = $response['response'] ?? [];

        $orderLines = $remoteOrder['lines'] ?? [];
        foreach ($orderLines as $remoteLine) {
            $localLine = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
                ->where('article_number', $remoteLine['inventory']['number'])
                ->where('quantity', $remoteLine['orderQty'])
                ->first();

            if ($localLine) {
                $localLine->update([
                    'line_key' => $remoteLine['lineNbr']
                ]);
            }
        }

        return [
            'success' => true,
            'message' => 'Purchase order created successfully.',
        ];
    }

    /**
     * Updates a purchase order in visma based on the local purchase order
     *
     * @param PurchaseOrder $purchaseOrder
     * @return array|true[]
     */
    public function updatePurchaseOrder(PurchaseOrder $purchaseOrder, ?bool $onHold = null, bool $fetchAfterUpdate = true): array
    {
        $purchaseOrder->refresh();

        // Fetch purchase order from Visma.net so that we can detect changes
        $response = $this->callAPI('GET', '/v1/purchaseorder/' . $purchaseOrder->order_number);
        $remoteOrder = $response['response'] ?? [];

        if (empty($remoteOrder['orderNbr'])) {
            return ['success' => false, 'message' => 'Remote order could not be found.'];
        }

        $lines = [];
        $processedLinesKeys = [];

        foreach ($remoteOrder['lines'] as $remoteLine) {
            $localLine = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
                ->where('line_key', $remoteLine['lineNbr'])
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
            }

            $processedLinesKeys[] = $remoteLine['lineNbr'];
        }

        // Add new order lines to Visma.net
        $newOrderLines = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
            ->whereNotIn('line_key', $processedLinesKeys)
            ->get();

        if ($newOrderLines) {
            foreach ($newOrderLines as $newOrderLine) {
                $lines[] = [
                    'operation' => 'Insert',
                    'lineNumber' => ['value' => $newOrderLine->line_key],
                    'inventory' => ['value' => $newOrderLine->article_number],
                    'lineType' => ['value' => 'GoodsForInventory'],
                    'lineDescription' => ['value' => $newOrderLine->description],
                    'orderQty' => ['value' => $newOrderLine->quantity],
                    'unitCost' => ['value' => $newOrderLine->unit_cost],
                    'amount' => ['value' => $newOrderLine->amount],
                    'promised' => ['value' => $newOrderLine->promised_date],
                ];
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

        // Fetch the order from Visma to get updated status
        if ($fetchAfterUpdate) {
            $this->fetchPurchaseOrders('', $purchaseOrder->order_number);
        }

        return ['success' => true, 'message' => ''];
    }

    public function fetchPurchaseOrders(string $updatedAfter = '', string $orderNumber = '')
    {
        // TODO: Move the called function to this service class
        $vismaNetController = new VismaNetController();
        $vismaNetController->fetchPurchaseOrders($updatedAfter, $orderNumber);
    }

    public function releasePurchaseOrderReceipt(string $receiptNumber): array
    {
        return $this->callAPI('POST', '/v1/PurchaseReceipt/' . $receiptNumber . '/action/release');
    }

    public function createPurchaseOrderReceipt(PurchaseOrder $purchaseOrder, PurchaseOrderShipment $purchaseOrderShipment): array
    {
        $postData = [
            'receiptType' => ['value' => 'PoReceipt'],
            'receiptNbr' => ['values' => (string) $purchaseOrderShipment->id],
            'hold' => ['value' => false],
            'date' => ['value' => (new DateTime())->format('Y-m-d H:i:s')],
            'warehouseId' => ['value' => self::WAREHOUSE_ID],
            'supplierId' => ['value' => $purchaseOrder->supplier->number],
            'currency' => ['value' => $purchaseOrder->currency],
            'lines' => [],
        ];

        $lines = PurchaseOrderLine::where('purchase_order_shipment_id', $purchaseOrderShipment->id)->get();

        $lineNbr = 1;
        foreach ($lines as $line) {
            $postData['lines'][] = [
                'operation' => 'Insert',
                'lineNbr' => ['value' => $lineNbr++],
                'lineType' => ['value' => 'GoodsForInventory'],
                'inventoryId' => ['value' => $line->article_number],
                'warehouseId' => ['value' => ($purchaseOrder->is_direct ? self::WAREHOUSE_DIRECT_ID : self::WAREHOUSE_ID)],
                'transactionDescription' => ['value' => $line->description],
                'receiptQty' => ['value' => (int) $line->quantity_received],
                'unitCost' => ['value' => $line->unit_cost],
                'amount' => ['value' => ($line->unit_cost * $line->quantity_received)],
                'poOrderNbr' => ['value' => $purchaseOrder->order_number],
                'poOrderType' => ['value' => 'RegularOrder'],
                'poOrderLineNbr' => ['value' => $line->line_key],
                'completePoLine' => ['value' => ($line->quantity == $line->quantity_received)],
            ];
        }

        $response = $this->callAPI('POST', '/v1/PurchaseReceipt', $postData);

        Log::info('/v1/PurchaseReceipt ' . json_encode(['payload' => $postData, 'response' => $response]));

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create purchase order receipt: ' . ($response['response']['message'] ?? 'Unknown error'),
            ];
        }

        // Fetch the purchase order receipt number
        $response = $this->callAPI('GET', '/v1/PurchaseReceipt', [
            'poOrderNbr' => $purchaseOrder->order_number
        ]);

        $receiptNumber = null;
        $compareTime = 0;
        $receipts = $response['response'] ?? [];
        foreach ($receipts as $receipt) {
            $lastModifiedDateTime = $receipt['lastModifiedDateTime'] ?? null;
            if (!$lastModifiedDateTime) continue;

            $modTime = strtotime($lastModifiedDateTime);
            if ($modTime > $compareTime) {
                $receiptNumber = $receipt['receiptNbr'] ?? null;
            }
        }

        $this->fetchPurchaseOrders('', $purchaseOrder->order_number);

        return [
            'success' => true,
            'message' => '',
            'receiptNumber' => $receiptNumber
        ];
    }
}
