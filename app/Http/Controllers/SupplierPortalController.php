<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderPublisher;
use App\Services\SupplierInvoiceService;
use App\Services\SupplierPortal\SupplierPortalAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SupplierPortalController extends Controller
{
    public function index(Request $request)
    {
        $supplier = SupplierPortalAccessService::getActiveSupplier();

        // Fetch purchase orders
        $openOrders = $this->callAPI(PurchaseOrderController::class, 'getOpen', [new Request()])['data'];
        $pendingOrders = $this->callAPI(PurchaseOrderController::class, 'getPending', [new Request()])['data'];

        $purchaseOrders = [
            'open' => array_merge($openOrders, $pendingOrders),
            'closed' => $this->callAPI(PurchaseOrderController::class, 'getClosed', [new Request()])['data'],
        ];

        // Filter only supplier purchase orders
        if (!App::environment('local')) {
            foreach ($purchaseOrders as $key => $items) {
                $purchaseOrders[$key] = array_filter($items, function ($order) use ($supplier) {
                    return $order['supplier_number'] === $supplier->number;
                });
            }
        }

        $breadcrumbs = [
            'Purchase Orders' => ''
        ];

        return view('supplierPortal.pages.index', compact('breadcrumbs', 'purchaseOrders'));
    }

    public function order(PurchaseOrder $purchaseOrder)
    {
        $supplier = SupplierPortalAccessService::getActiveSupplier();
        if (!App::environment('local') && $supplier->number !== $purchaseOrder->supplier_number) {
            abort(404);
        }

        $purchaseOrder->update(['viewed_at' => date('Y-m-d H:i:s')]);

        $breadcrumbs = [
            'Purchase Orders' => route('supplierPortal.purchaseOrders.index'),
            'Order #' . $purchaseOrder->id => '',
        ];

        return view('supplierPortal.pages.purchaseOrder', compact('breadcrumbs', 'purchaseOrder'));
    }

    public function postOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $supplier = SupplierPortalAccessService::getActiveSupplier();
        if (!App::environment('local') && $supplier->number !== $purchaseOrder->supplier_number) {
            abort(404);
        }

        switch ($purchaseOrder->getPortalStatus()) {
            case PurchaseOrder::PORTAL_STATUS_UNCONFIRMED:
                $response = $this->publishOrder($request, $purchaseOrder);
                break;

            case PurchaseOrder::PORTAL_STATUS_OPEN:
                $response = $this->updateOrder($request, $purchaseOrder);
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Invalid order status.']);
                break;
        }


        if ($response['success']) {
            $this->setPurchaseOrderStatus($purchaseOrder);

            $purchaseOrder->update([
                'status_confirmed_by_supplier' => 1
            ]);
        }

        return response()->json($response);
    }

    public function uploadInvoice(Request $request, PurchaseOrder $purchaseOrder)
    {
        $supplier = SupplierPortalAccessService::getActiveSupplier();
        if (!App::environment('local') && $supplier->number !== $purchaseOrder->supplier_number) {
            abort(404);
        }

        $request->validate([
            'invoice' => 'required|file|mimes:pdf'
        ]);

        if ($request->has('invoice')) {
            $file = $request->file('invoice');
            $invoiceLines = $request->input('purchase_order_lines') ?: [];

            $supplierInvoiceService = new SupplierInvoiceService();
            $supplierInvoiceService->uploadInvoice(
                $purchaseOrder,
                $invoiceLines,
                $file
            );
        }

        $this->setPurchaseOrderStatus($purchaseOrder);

        return redirect()->route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id]);
    }

    private function publishOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $publisher = new PurchaseOrderPublisher();
        return $publisher->publishOrder($purchaseOrder, $request->input('items'));
    }

    private function updateOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $publisher = new PurchaseOrderPublisher();
        return $publisher->updateOrder($purchaseOrder, $request->input('items'));
    }

    private function setPurchaseOrderStatus(PurchaseOrder $purchaseOrder)
    {
        $providedShippingDetails = 1;
        $uploadedInvoice = 1;

        foreach ($purchaseOrder->lines as $line) {
            if (!$line->promised_date || !$line->tracking_number) {
                // Missing shipping details
                $providedShippingDetails = 0;
            }

            if (!$line->invoice_id) {
                // Missing invoice
                $uploadedInvoice = 0;
            }
        }

        $purchaseOrder->update([
            'status_shipping_details' => $providedShippingDetails,
            'status_invoice_uploaded' => $uploadedInvoice
        ]);
    }

    private function callAPI($controller, $method, $params = [])
    {
        $instance = new $controller();
        $response = call_user_func_array([$instance, $method], $params);

        $response = json_decode($response->getContent(), true);

        return $response['data'] ?? [];
    }
}
