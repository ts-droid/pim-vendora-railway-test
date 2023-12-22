<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
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
}
