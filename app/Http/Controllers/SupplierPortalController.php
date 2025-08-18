<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use App\Models\SupplierInvoice;
use App\Services\PurchaseOrderPublisher;
use App\Services\PurchaseOrderService;
use App\Services\SupplierInvoiceService;
use App\Services\SupplierPortal\SupplierPortalAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SupplierPortalController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $supplier = SupplierPortalAccessService::getActiveSupplier();

        $apiRequest = new Request([
            'supplier_number' => $supplier->number
        ]);

        // Fetch purchase orders
        $openOrders = $this->callAPI(PurchaseOrderController::class, 'getOpen', [$apiRequest])['data'];
        $pendingOrders = $this->callAPI(PurchaseOrderController::class, 'getPending', [$apiRequest])['data'];

        $purchaseOrders = [
            'open' => array_merge($openOrders, $pendingOrders),
            'closed' => $this->callAPI(PurchaseOrderController::class, 'getClosed', [$apiRequest])['data'],
        ];

        // Filter by search term
        if ($search) {
            foreach ($purchaseOrders as $key => $items) {
                $purchaseOrders[$key] = array_filter($items, function ($order) use ($search) {
                    return (str_contains($order['id'], $search)
                        || str_contains($order['supplier_order_number'], $search));
                });
            }
        }

        $breadcrumbs = [
            'Purchase Orders' => ''
        ];

        return view('supplierPortal.pages.index', compact('breadcrumbs', 'purchaseOrders'));
    }

    public function order(Request $request, PurchaseOrder $purchaseOrder)
    {
        $supplier = SupplierPortalAccessService::getActiveSupplier();
        if (!App::environment('local') && $supplier->number !== $purchaseOrder->supplier_number) {
            abort(404);
        }

        $shippingInstructions = json_decode($purchaseOrder->shipping_instructions ?: '[]', true);
        $selectedShippingInstructions = null;
        if ($purchaseOrder->shipping_instructions && isset($shippingInstructions[$purchaseOrder->shipping_instructions])) {
            $selectedShippingInstructions = $shippingInstructions[$purchaseOrder->shipping_instructions];
        }

        $purchaseOrder->update(['viewed_at' => date('Y-m-d H:i:s')]);

        // Load all shipments for the purchase order
        $shipments = PurchaseOrderShipment::where('purchase_order_id', $purchaseOrder->id)->orderBy('id', 'DESC')->get();

        // Load all invoices for the purchase order
        $invoices = SupplierInvoice::where('purchase_order_id', $purchaseOrder->id)->orderBy('id', 'DESC')->get();

        // Load selected shipment
        $shipmentID = (int) $request->input('shipment_id', 0);
        $shipmentQuery = PurchaseOrderShipment::where('id', $shipmentID);

        $openShipment = null;
        if ($shipmentID && $shipmentQuery->exists()) {
            $openShipment = $shipmentQuery->first();
        }

        $breadcrumbs = [
            'Purchase Orders' => route('supplierPortal.purchaseOrders.index'),
            'Order #' . $purchaseOrder->id => '',
        ];

        return view('supplierPortal.pages.purchaseOrder', compact('breadcrumbs', 'purchaseOrder', 'shipments', 'invoices', 'openShipment', 'selectedShippingInstructions'));
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

        $updateData = [
            'supplier_order_number' => (string) $request->input('supplier_order_number', '')
        ];

        if ($response['success']) {
            PurchaseOrderService::setPurchaseOrderStatus($purchaseOrder);

            $updateData['status_confirmed_by_supplier'] = 1;
            $updateData['shipping_reminder_sent_at'] = date('Y-m-d H:i:s');
        }

        $purchaseOrder->update($updateData);

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

        PurchaseOrderService::setPurchaseOrderStatus($purchaseOrder);

        return redirect()->route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id]);
    }

    public function deleteInvoice(Request $request, PurchaseOrder $purchaseOrder, SupplierInvoice $supplierInvoice)
    {
        $supplier = SupplierPortalAccessService::getActiveSupplier();
        if (!App::environment('local') && $supplier->number !== $purchaseOrder->supplier_number) {
            abort(404);
        }

        if ($supplierInvoice->purchase_order_id !== $purchaseOrder->id) {
            abort(404);
        }

        $supplierInvoiceService = new SupplierInvoiceService();
        $supplierInvoiceService->deleteInvoice($supplierInvoice);

        PurchaseOrderService::setPurchaseOrderStatus($purchaseOrder);

        return redirect()->route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id]);
    }


    public function createShipment(Request $request, PurchaseOrder $purchaseOrder)
    {
        $supplier = SupplierPortalAccessService::getActiveSupplier();
        if (!App::environment('local') && $supplier->number !== $purchaseOrder->supplier_number) {
            abort(404);
        }

        $request->validate([
            'receipt' => 'required|file|mimes:pdf',
            'tracking_number' => 'required|string|max:255',
        ]);

        $orderLines = $request->input('purchase_order_lines') ?: [];
        $trackingNumber = $request->input('tracking_number');

        // Make sure at least one line is selected
        if (count($orderLines) === 0) {
            return redirect()->back()->with('error', 'Please select at least one order line to ship.');
        }

        // Upload the receipt
        $file = $request->file('receipt');

        $spaceFilename = DoSpacesController::store(
            time() . '_' . $file->getClientOriginalName(),
            $file->getContent(),
            false
        );

        // Store the shipment
        $purchaseOrderLines = PurchaseOrderLine::whereIn('id', $orderLines)->get();

        $purchaseOrderService = new PurchaseOrderService();
        $purchaseOrderShipment = $purchaseOrderService->createShipment(
            $purchaseOrder,
            [
                'receipt' => $spaceFilename,
                'tracking_number' => $trackingNumber
            ],
            $purchaseOrderLines
        );

        return redirect()->route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id, 'shipment_id' => $purchaseOrderShipment->id]);
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

    private function callAPI($controller, $method, $params = [])
    {
        $instance = new $controller();
        $response = call_user_func_array([$instance, $method], $params);

        $response = json_decode($response->getContent(), true);

        return $response['data'] ?? [];
    }
}
