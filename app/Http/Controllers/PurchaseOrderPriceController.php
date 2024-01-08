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

        // Remove the purchase order and order lines
        PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)->delete();

        $purchaseOrder->delete();

        echo 'The purchase order has been canceled and deleted.';
        exit;
    }
}
