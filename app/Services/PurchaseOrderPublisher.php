<?php

namespace App\Services;

use App\Actions\DispatchArticleUpdate;
use App\Jobs\MarkArticleEOL;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use App\Services\VismaNet\VismaNetPurchaseOrderService;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderPublisher
{
    const SHIPPING_DATE_BUFFER = 5; // Add 5 days to the shipping date as the ETA

    /**
     * Transform a draft order into a real order.
     *
     * @param PurchaseOrder $purchaseOrder
     * @return array|true[]
     */
    public function send(PurchaseOrder $purchaseOrder)
    {
        if (!$purchaseOrder->is_draft) {
            // Order is not a draft
            return ['success' => false, 'message' => 'Order is not a draft.'];
        }

        // Update the order date
        $purchaseOrder->update([
            'date' => date('Y-m-d')
        ]);

        // Send the order to Visma.net
        $purchaseOrderService = new VismaNetPurchaseOrderService();
        $createOrderResponse = $purchaseOrderService->createPurchaseOrder($purchaseOrder, true);

        if (!$createOrderResponse['success']) {
            return $createOrderResponse;
        }

        return ['success' => true, 'message' => 'Purchase order sent.'];
    }

    /**
     * Transform a draft order into a published order.
     *
     * @param PurchaseOrder $purchaseOrder
     * @return array
     */
    public function publishOrder(PurchaseOrder $purchaseOrder, array $items): array
    {
        if ($purchaseOrder->published_at) {
            // Order is not a draft
            return ['success' => false, 'message' => 'Order is already published.'];
        }

        // Process the items
        if ($items && count($items) > 0) {
            $response = $this->processItems($purchaseOrder, $items, true);

            if ($response['require_confirmation']) {
                // Order needs confirmation from admin before publishing
                // Return true, because it was just sent to admin for confirmation

                // Dispatch confirmation email to admin
                try {
                    Mail::to('purchasing@vendora.se')->queue(new \App\Mail\PurchaseOrderPriceChange($purchaseOrder, $response['updated_prices']));
                }
                catch (\Exception $e) {
                    log_data('Failed to send purchase order price change confirmation email. (Error: ' . $e->getMessage() . ')');
                }

                return ['success' => true, 'message' => ''];
            }
        }

        $purchaseOrder->update([
            'date' => date('Y-m-d')
        ]);

        // Update and un-park the order in Visma.net
        $purchaseOrderService = new VismaNetPurchaseOrderService();
        $updateResult = $purchaseOrderService->updatePurchaseOrder($purchaseOrder, false);

        if (!$updateResult['success']) {
            return $updateResult;
        }

        // Fetch purchase order to update with data from Visma.net
        $purchaseOrderService = new VismaNetPurchaseOrderService();
        $purchaseOrderService->fetchPurchaseOrders('', $purchaseOrder->order_number);

        // Update published timestamp
        $purchaseOrder->update([
            'published_at' => date('Y-m-d H:i:s')
        ]);

        // Fetch the initial shipment id
        $shipmentID = (int) PurchaseOrderShipment::where('purchase_order_id', $purchaseOrder->id)->value('id');

        return [
            'success' => true,
            'message' => '',
            'shipment_id' => ($shipmentID ?: null),
        ];
    }

    /**
     * Updates an already published purchase order
     *
     * @param PurchaseOrder $purchaseOrder
     * @param array $items
     * @return array
     */
    public function updateOrder(PurchaseOrder $purchaseOrder, array $items): array
    {
        if ($purchaseOrder->is_draft) {
            // Can't update a draft order
            return ['success' => false, 'message' => 'Order is a draft.'];
        }

        $this->processItems($purchaseOrder, $items);

        // Send update to Visma.net
        $purchaseOrderService = new VismaNetPurchaseOrderService();
        $updateResult = $purchaseOrderService->updatePurchaseOrder($purchaseOrder);

        if (!$updateResult['success']) {
            log_data('Failed to update purchase order. (Error: ' . $updateResult['message'] . ')');
        }

        // Fetch purchase order to update with data from Visma.net
        $purchaseOrderService->fetchPurchaseOrders('', $purchaseOrder->order_number);

        return ['success' => true];
    }

    /**
     * Process the items posted from the user form
     *
     * @param PurchaseOrder $purchaseOrder
     * @param array $items
     * @return array
     */
    private function processItems(PurchaseOrder &$purchaseOrder, array $items, bool $publish = false): array
    {
        $response = [
            'require_confirmation' => false,
            'updated_prices' => [],
        ];

        $orderPromisedDate = $purchaseOrder->promised_date;
        $eolArticleNumbers = [];

        foreach ($items as $item) {
            $orderLine = PurchaseOrderLine::find($item['id']);

            if (!$orderLine) {
                continue;
            }

            // Handle non-confirming statuses
            switch ($item['status'] ?? '') {
                case 'eol':
                    $eolArticleNumbers[] = $orderLine->article_number;
                    $orderLine->delete();
                    continue 2;

                case 'decline':
                    $orderLine->delete();
                    continue 2;
            }

            // Set the shipping date
            $shippingDateBuffer = ($purchaseOrder->supplier->general_delivery_time ?? 0) ?: self::SHIPPING_DATE_BUFFER;
            $shippingDate = date('Y-m-d', (strtotime($item['shipping_date']) + (86400 * $shippingDateBuffer)));

            if (!$orderPromisedDate || $orderPromisedDate > $shippingDate) {
                $orderPromisedDate = $shippingDate;
            }

            // Update the order line unit cost
            $unitCost = $orderLine->unit_cost;
            $oldUnitCost = $orderLine->old_unit_cost;
            $quantity = (int) ($item['quantity'] ?? $orderLine->quantity);

            if (isset($item['unit_cost'])) {
                $unitCost = (float) str_replace(',', '.', $item['unit_cost']);

                if ($unitCost != $orderLine->unit_cost) {
                    $response['require_confirmation'] = true;

                    $response['updated_prices'][] = [
                        'order_line_id' => $orderLine->id,
                        'from' => $orderLine->unit_cost,
                        'to' => $unitCost,
                    ];

                    $oldUnitCost = $orderLine->unit_cost;
                }
            }

            // Update local order line
            $orderLine->update([
                'promised_date' => $shippingDate,
                'unit_cost' => $unitCost,
                'old_unit_cost' => $oldUnitCost,
                'amount' => round(($unitCost * $quantity), 2),
                'tracking_number' => $item['tracking_number'] ?? null,
            ]);

            // Dispatch article update so ETA gets pushed to external systems
            (new DispatchArticleUpdate)->execute($orderLine->article->id, false, [], true);
        }

        // Calculate the order total
        $orderTotal = (float) PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)->sum('amount');

        // Update the order promised date
        $purchaseOrder->update([
            'promised_date' => $orderPromisedDate,
            'total' => $orderTotal,
            'is_confirmed' => $response['require_confirmation'] ? 0 : $purchaseOrder->is_confirmed,
        ]);

        // Mark articles as EOL
        if ($eolArticleNumbers) {
            MarkArticleEOL::dispatch($eolArticleNumbers);
        }

        // Refresh the order before returning
        $purchaseOrder->refresh();

        if ($publish) {
            $shipmentDate = '';
            $shipmentLines = [];

            foreach ($purchaseOrder->lines as $orderLine) {
                if ($shipmentDate && $shipmentDate < $orderLine->promised_date) {
                    continue;
                }

                $shipmentLines = ($shipmentDate == $orderLine->promised_date) ? $shipmentLines : [];
                $shipmentLines[] = $orderLine;

                $shipmentDate = $orderLine->promised_date;
            }

            if (count($shipmentLines) > 0) {
                // Create initial shipment for the order
                $purchaseOrderService = new PurchaseOrderService();
                $purchaseOrderShipment = $purchaseOrderService->createShipment(
                    $purchaseOrder,
                    [
                        'receipt' => null,
                        'tracking_number' => $shipmentLines[0]->tracking_number ?? '',
                    ],
                    $shipmentLines
                );
            }
        }

        return $response;
    }
}
