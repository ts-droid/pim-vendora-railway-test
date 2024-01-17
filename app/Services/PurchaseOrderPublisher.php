<?php

namespace App\Services;

use App\Jobs\MarkArticleEOL;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\VismaNet\VismaNetPurchaseOrderService;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderPublisher
{
    /**
     * Transform a draft order into a published order.
     *
     * @param PurchaseOrder $purchaseOrder
     * @return array
     */
    public function publishOrder(PurchaseOrder $purchaseOrder, array $items): array
    {
        if (!$purchaseOrder->is_draft) {
            // Order is not a draft
            return ['success' => false, 'message' => 'Order is not a draft.'];
        }

        if (!$purchaseOrder->is_confirmed) {
            // Order is not confirmed by admin
            return ['success' => false, 'message' => 'Order is not confirmed by Vendora.'];
        }

        // Process the items
        if ($items) {
            $response = $this->processItems($purchaseOrder, $items);

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

                return ['success' => true];
            }
        }

        // Send the order to Visma.net
        $purchaseOrderService = new VismaNetPurchaseOrderService();
        $createOrderResponse = $purchaseOrderService->createPurchaseOrder($purchaseOrder);

        if (!$createOrderResponse['success']) {
            return $createOrderResponse;
        }

        // Fetch purchase orders to update with data from Visma.net
        $purchaseOrderService->fetchPurchaseOrders('', $purchaseOrder->order_number);

        // Update published timestamp
        $purchaseOrder->update([
            'published_at' => date('Y-m-d H:i:s')
        ]);

        return ['success' => true];
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
    private function processItems(PurchaseOrder $purchaseOrder, array $items): array
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

            // Handle EOL items
            if ($item['status'] == 'eol') {
                $eolArticleNumbers[] = $orderLine->article_number;

                $orderLine->delete();

                continue;
            }

            // Set the shipping date
            $shippingDate = date('Y-m-d', (strtotime($item['shipping_date']) + (86400 * 5))); // Add 5 days to the promised date

            $orderLine->update([
                'promised_date' => $shippingDate
            ]);

            if (!$orderPromisedDate || $orderPromisedDate > $shippingDate) {
                $orderPromisedDate = $shippingDate;
            }

            // Update the order line unit cost
            if (isset($item['unit_cost'])) {
                $unitCost = (float) str_replace(',', '.', $item['unit_cost']);

                if ($unitCost != $orderLine->unit_cost) {
                    $response['require_confirmation'] = true;

                    $response['updated_prices'][] = [
                        'order_line_id' => $orderLine->id,
                        'from' => $orderLine->unit_cost,
                        'to' => $unitCost,
                    ];

                    $orderLine->update([
                        'unit_cost' => $unitCost,
                        'amount' => round(($unitCost * $orderLine->quantity), 2)
                    ]);
                }
            }
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

        return $response;
    }
}
