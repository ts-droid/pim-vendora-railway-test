<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\PurchaseOrderPublisher;
use App\Services\SupplierArticlePriceService;
use Illuminate\Http\Request;

class PurchaseOrderPriceController extends Controller
{
    public function confirm(Request $request, PurchaseOrder $purchaseOrder, string $hash)
    {
        if ($hash !== $purchaseOrder->getHash()) {
            abort(404);
        }

        // Mark the purchase order as confirmed
        $purchaseOrder->update([
            'is_confirmed' => 1
        ]);

        // Update the prices list
        if ($purchaseOrder->lines) {
            $priceService = new SupplierArticlePriceService();

            foreach ($purchaseOrder->lines as $line) {
                $priceService->createSupplierArticlePrice([
                    'article_number' => (string) $line->article_number,
                    'price' => (float) $line->unit_cost,
                    'currency' => (string) $purchaseOrder->currency
                ]);
            }
        }

        // Publish the purchase order
        $publisher = new PurchaseOrderPublisher();
        $response = $publisher->publishOrder($purchaseOrder, []);

        if (!$response['success']) {
            echo 'Failed to accept the purchase order. Please try again or contact admin.';
            exit;
        }

        echo 'The prices and purchase order has been confirmed.';
        exit;
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder, string $hash)
    {
        if ($hash !== $purchaseOrder->getHash()) {
            abort(404);
        }

        // Revert all the price changes
        $total = 0;

        if ($purchaseOrder->lines) {
            foreach ($purchaseOrder->lines as $purchaseOrderLine) {
                if (!$purchaseOrderLine->old_unit_cost) {
                    $total += $purchaseOrderLine->unit_cost;
                    continue;
                }

                $purchaseOrderLine->update([
                    'unit_cost' => $purchaseOrderLine->old_unit_cost,
                    'old_unit_cost' => 0,
                ]);

                $total += $purchaseOrderLine->old_unit_cost;
            }
        }

        // Mark the purchase order as confirmed
        $purchaseOrder->update([
            'is_confirmed' => 1,
            'amount' => $total
        ]);

        $purchaseOrder->refresh();

        // Publish the purchase order
        $publisher = new PurchaseOrderPublisher();
        $response = $publisher->publishOrder($purchaseOrder, []);

        if (!$response['success']) {
            echo 'Failed to publish the purchase order. Please try again or contact admin.';
            exit;
        }

        echo 'The price changes have been rejected and purchase order has been confirmed with the old prices.';
        exit;
    }
}
