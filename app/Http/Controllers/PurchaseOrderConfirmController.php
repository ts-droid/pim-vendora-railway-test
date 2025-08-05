<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderPublisher;
use Illuminate\Http\Request;

class PurchaseOrderConfirmController extends Controller
{
    public function confirm(PurchaseOrder $purchaseOrder, string $hash)
    {
        if ($hash !== $purchaseOrder->getHash()) {
            abort(404);
        }

        if ($purchaseOrder->published_at) {
            return view('purchaseOrders.confirmDone', compact('purchaseOrder'));
        }

        return view('purchaseOrders.confim', compact('purchaseOrder'));
    }

    public function postConfirm(Request $request, PurchaseOrder $purchaseOrder, string $hash)
    {
        if ($hash !== $purchaseOrder->getHash()) {
            return response()->json([
                'success' => false,
                'message' => 'Wrong hash.'
            ]);
        }

        if ($purchaseOrder->published_at) {
            return response()->json([
                'success' => false,
                'message' => 'Order have already been confirmed.'
            ]);
        }

        // Confirm the purchase order
        $purchaseOrderPublisher = new PurchaseOrderPublisher();
        $response = $purchaseOrderPublisher->publishOrder(
            $purchaseOrder,
            $request->input('items')
        );

        if (!$response['success']) {
            return response()->json([
                'success' => false,
                'message' => $response['message'],
                'meta' => $response['meta'] ?? []
            ]);
        }

        // Update order status
        $purchaseOrder->update([
            'status_confirmed_by_supplier' => 1,
            'status_shipping_details' => 1,
        ]);

        return response()->json([
            'success' => true
        ]);
    }
}
