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

        if (!$purchaseOrder->is_draft) {
            abort(404);
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

        if (!$purchaseOrder->is_draft) {
            return response()->json([
                'success' => false,
                'message' => 'Order have already been confirmed.'
            ]);
        }

        // Confirm the purchase order
        $purchaseOrderPublisher = new PurchaseOrderPublisher();
        $response = $purchaseOrderPublisher->publishOrder(
            $purchaseOrder,
            $request->post('items')
        );

        if (!$response['success']) {
            return response()->json([
                'success' => false,
                'message' => $response['message']
            ]);
        }

        return response()->json([
            'success' => true
        ]);
    }
}
