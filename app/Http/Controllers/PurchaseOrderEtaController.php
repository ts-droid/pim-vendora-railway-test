<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\PurchaseOrderPublisher;
use Illuminate\Http\Request;

class PurchaseOrderEtaController extends Controller
{
    public function index(Request $request, PurchaseOrder $purchaseOrder, string $hash)
    {
        if ($hash !== $purchaseOrder->getHash()) {
            abort(404);
        }

        $orderLineIDs = explode(',', $request->get('orderLines'));

        $orderLineIDs = array_map(function($id) {
            return (int) $id;
        }, $orderLineIDs);

        return view('purchaseOrders.orderLineEta', compact('purchaseOrder', 'orderLineIDs'));
    }

    public function post(Request $request, PurchaseOrder $purchaseOrder, string $hash)
    {
        if ($hash !== $purchaseOrder->getHash()) {
            return response()->json([
                'success' => false,
                'message' => 'Wrong hash.'
            ]);
        }

        $publisher = new PurchaseOrderPublisher();
        $response = $publisher->updateOrder(
            $purchaseOrder,
            $request->input('items')
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
