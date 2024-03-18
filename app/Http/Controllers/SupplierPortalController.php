<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderPublisher;
use App\Services\SupplierPortal\SupplierPortalAccessService;
use Illuminate\Http\Request;

class SupplierPortalController extends Controller
{
    public function index(Request $request)
    {
        $supplier = SupplierPortalAccessService::getActiveSupplier();

        // Fetch purchase orders
        $purchaseOrders = [];

        $purchaseOrders['unconfirmed'] = PurchaseOrder::where('supplier_id', $supplier->external_id)
            ->where('is_po_system', '=', 1)
            ->whereNull('published_at')
            ->orderBy('date', 'desc')
            ->get();

        $purchaseOrders['confirmed'] = PurchaseOrder::where('supplier_id', $supplier->external_id)
            ->where('is_po_system', '=', 1)
            ->whereNotNull('published_at')
            ->whereHas('lines', function ($query) {
                $query->where('is_completed', '=', 0);
            })
            ->orderBy('date', 'desc')
            ->get();

        $purchaseOrders['closed'] = PurchaseOrder::where('supplier_id', $supplier->external_id)
            ->where('is_po_system', '=', 1)
            ->whereNotNull('published_at')
            ->whereDoesntHave('lines', function ($query) {
                $query->where('is_completed', '=', 0);
            })
            ->orderBy('date', 'desc')
            ->get();

        return view('supplierPortal.pages.index', compact('purchaseOrders'));
    }

    public function order(PurchaseOrder $purchaseOrder, string $hash)
    {
        if ($purchaseOrder->getHash() !== $hash) {
            abort(404);
        }

        return view('supplierPortal.pages.purchaseOrder', compact('purchaseOrder'));
    }

    public function confirm(Request $request, PurchaseOrder $purchaseOrder, string $hash)
    {
        if ($purchaseOrder->getHash() !== $hash) {
            abort(404);
        }

        // Confirm the purchase order
        $purchaseOrderPublisher = new PurchaseOrderPublisher();
        $response = $purchaseOrderPublisher->publishOrder($purchaseOrder, $request->input('items'));

        return response()->json($response);
    }
}
