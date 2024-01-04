<?php

namespace App\Services;

use App\Jobs\MarkArticleEOL;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\VismaNet\VismaNetPurchaseOrderService;

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

        $this->processItems($purchaseOrder, $items);

        // Send the order to Visma.net
        $purchaseOrderService = new VismaNetPurchaseOrderService();
        $createOrderResponse = $purchaseOrderService->createPurchaseOrder($purchaseOrder);

        if (!$createOrderResponse['success']) {
            return $createOrderResponse;
        }

        // Fetch purchase orders to update with data from Visma.net
        $purchaseOrderService->fetchPurchaseOrders('', $purchaseOrder->order_number);

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
     * @return void
     */
    private function processItems(PurchaseOrder $purchaseOrder, array $items): void
    {
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
        }

        // Update the order promised date
        $purchaseOrder->update([
            'promised_date' => $orderPromisedDate
        ]);

        // Mark articles as EOL
        if ($eolArticleNumbers) {
            MarkArticleEOL::dispatch($eolArticleNumbers);
        }

        // Refresh the order before returning
        $purchaseOrder->refresh();
    }
}
