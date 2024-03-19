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

        $breadcrumbs = [
            'Purchase Orders' => ''
        ];

        return view('supplierPortal.pages.index', compact('breadcrumbs', 'purchaseOrders'));
    }

    public function order(PurchaseOrder $purchaseOrder, string $hash)
    {
        if ($purchaseOrder->getHash() !== $hash) {
            abort(404);
        }

        $breadcrumbs = [
            'Purchase Orders' => route('supplierPortal.purchaseOrders.index'),
            'Order ' . $purchaseOrder->order_number => '',
        ];

        return view('supplierPortal.pages.purchaseOrder', compact('breadcrumbs', 'purchaseOrder'));
    }

    public function postOrder(Request $request, PurchaseOrder $purchaseOrder, string $hash)
    {
        if ($purchaseOrder->getHash() !== $hash) {
            abort(404);
        }

        switch ($purchaseOrder->getPortalStatus()) {
            case PurchaseOrder::PORTAL_STATUS_UNCONFIRMED:
                return $this->publishOrder($request, $purchaseOrder);

            case PurchaseOrder::PORTAL_STATUS_OPEN:
                return $this->updateOrder($request, $purchaseOrder);
        }

        return response()->json(['success' => false, 'message' => 'Invalid order status.']);
    }

    private function publishOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $publisher = new PurchaseOrderPublisher();
        $response = $publisher->publishOrder($purchaseOrder, $request->input('items'));

        return response()->json($response);
    }

    private function updateOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $publisher = new PurchaseOrderPublisher();
        $response = $publisher->updateOrder($purchaseOrder, $request->input('items'));

        return response()->json($response);
    }
}
