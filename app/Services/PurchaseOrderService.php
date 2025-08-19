<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Services\VismaNet\VismaNetPurchaseOrderService;
use App\Services\VismaNet\VismaNetSalesOrderService;
use App\Services\VismaNet\VismaNetShipmentService;
use App\Services\WMS\StockItemService;
use App\Services\WMS\StockPlaceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class PurchaseOrderService
{
    /**
     * @param PurchaseOrderLine $purchaseOrderLine
     * @param int $newQuantity
     * @return array
     */
    public function splitOrderLine(PurchaseOrderLine $purchaseOrderLine, int $newQuantity): array
    {
        $purchaseOrderLine->refresh();

        if ($newQuantity <= 0) {
            return [
                'success' => false,
                'old_line' => null,
                'new_line' => null,
                'error_message' => 'New quantity must be greater than zero.'
            ];
        }

        if ($purchaseOrderLine->quantity <= 1) {
            return [
                'success' => false,
                'old_line' => null,
                'new_line' => null,
                'error_message' => 'Cannot split order line with quantity less than or equal to 1.'
            ];
        }

        if ($newQuantity >= $purchaseOrderLine->quantity) {
            return [
                'success' => false,
                'old_line' => null,
                'new_line' => null,
                'error_message' => 'New quantity (' . $newQuantity . ') must be less than the original quantity (' . $purchaseOrderLine->quantity . ').'
            ];
        }

        // Calculate the new line key
        $maxLineKey = PurchaseOrderLine::where('purchase_order_id', $purchaseOrderLine->purchase_order_id)
            ->selectRaw('MAX(CAST(line_key AS UNSIGNED)) as max_line_key')
            ->value('max_line_key');

        $newLineKey = $maxLineKey + 1;

        // Deduct the quantity from the original line
        $purchaseOrderLine->update([
            'quantity' => $purchaseOrderLine->quantity - $newQuantity,
        ]);

        // Copy to a new line
        $newLine = $purchaseOrderLine->replicate();
        $newLine->fill([
            'line_key' => $newLineKey,
            'quantity' => $newQuantity,
            'quantity_received' => 0,
            'suggested_quantity' => 0,
            'suggested_quantity_master' => 0,
            'suggested_quantity_month' => 0,
            'suggested_quantity_month_master' => 0,
            'suggested_quantity_month_inner' => 0,
            'suggested_quantity_inner' => 0,
            'amount' => round($newQuantity * $newLine->unit_cost, 2),
            'promised_date' => '',
            'is_vip' => 0,
            'is_completed' => 0,
            'is_canceled' => 0,
            'reminder_sent_at' => null,
            'tracking_number' => null,
            'invoice_id' => 0,
            'is_shipped' => 0,
            'purchase_order_shipment_id' => 0
        ]);

        $newLine->save();
        $newLine->refresh();
        $purchaseOrderLine->refresh();

        // Send update to Visma.net
        $purchaseOrder = PurchaseOrder::find($purchaseOrderLine->purchase_order_id);

        $vismaNetPurchaseOrderService = new VismaNetPurchaseOrderService();
        $vismaNetPurchaseOrderService->updatePurchaseOrder($purchaseOrder);

        return [
            'success' => true,
            'old_line' => $purchaseOrderLine,
            'new_line' => $newLine,
            'error_message' => null
        ];
    }

    /**
     * @param PurchaseOrderShipment $purchaseOrderShipment
     * @param array $quantities
     * @return array
     */
    public function deliverShipment(PurchaseOrderShipment $purchaseOrderShipment, array $quantities): array
    {
        $purchaseOrderShipment->refresh();

        if (!$quantities) {
            return [
                'success' => false,
                'error_message' => 'No quantities provided.'
            ];
        }

        $lineIDs = PurchaseOrderLine::where('purchase_order_shipment_id', $purchaseOrderShipment->id)
            ->pluck('id');

        DB::beginTransaction();

        foreach ($lineIDs as $lineID) {
            $qty = (int) ($quantities[$lineID] ?? 0);
            if (!$qty) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error_message' => 'Quantity not provided for all lines.'
                ];
            }

            $orderLine = PurchaseOrderLine::find($lineID);

            if ($qty > $orderLine->quantity) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error_message' => 'Quantity cannot be greater than the ordered quantity.'
                ];
            }

            // If received quantity is less than expected, split the missing quantity to a new line
            if ($qty < $orderLine->quantity) {
                $missingQty = $orderLine->quantity - $qty;
                $splitResponse = $this->splitOrderLine($orderLine, $missingQty);

                if (!$splitResponse['success']) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'error_message' => $splitResponse['error_message']
                    ];
                }
            }

            $orderLine->update([
                'is_completed' => 1,
                'quantity_received' => $qty,
            ]);
        }

        // Update the purchase order
        $quantityOpen = (int) PurchaseOrderLine::select(DB::raw('SUM(quantity - quantity_received) AS quantity_open'))
            ->where('purchase_order_id', $purchaseOrderShipment->purchaseOrder->id)
            ->first()->quantity_open;

        $purchaseOrderShipment->purchaseOrder->update([
            'status_received' => ($quantityOpen === 0) ? 1 : 0
        ]);

        DB::commit();

        // Create a purchase order receipt in Visma.net
        $vismaNetPurchaseOrderService = new VismaNetPurchaseOrderService();
        $response = $vismaNetPurchaseOrderService->createPurchaseOrderReceipt($purchaseOrderShipment->purchaseOrder, $purchaseOrderShipment);
        if (!$response['success']) {
            return [
                'success' => false,
                'error_message' => $response['message']
            ];
        }

        // Add the items to in-delivery stock place
        $stockPlaceService = new StockPlaceService();
        $stockItemService = new StockItemService();

        $indeliveryCompartment = $stockPlaceService->getCompartmentByIdentifier('INLEV:1');

        foreach ($lineIDs as $lineID) {
            $line = PurchaseOrderLine::find($lineID);

            $stockItemService->addStockItem(
                $line->article_number,
                $line->quantity_received,
                $indeliveryCompartment,
                get_display_name()
            );

            DB::table('articles')
                ->where('article_number', $line->article_number)
                ->increment('stock_manageable', $line->quantity_received);
        }

        return [
            'success' => true,
            'error_message' => null
        ];
    }

    /**
     * @param PurchaseOrder $purchaseOrder
     * @param array $data
     * @param PurchaseOrderLine[] $lines
     * @return PurchaseOrderShipment
     */
    public function createShipment(PurchaseOrder $purchaseOrder, array $data = [], mixed $lines = []): PurchaseOrderShipment
    {
        $purchaseOrder->refresh();

        $receipt = $data['receipt'] ?? null;
        $trackingNumber = $data['tracking_number'] ?? null;

        $shipment = PurchaseOrderShipment::create([
            'purchase_order_id' => $purchaseOrder->id,
            'receipt' => $receipt,
            'tracking_number' => $trackingNumber,
        ]);

        foreach ($lines as $line) {
            $line->update([
                'is_shipped' => 1,
                'tracking_number' => $trackingNumber,
                'purchase_order_shipment_id' => $shipment->id
            ]);
        }

        self::setPurchaseOrderStatus($purchaseOrder);

        return $shipment;
    }

    public function cancelRow(int $lineID): array
    {
        $purchaseOrderLine = PurchaseOrderLine::find($lineID);
        if (!$purchaseOrderLine) {
            return [
                'success' => false,
                'error_message' => 'Could not find the order line.'
            ];
        }

        $purchaseOrder = $purchaseOrderLine->purchaseOrder;

        $purchaseOrderLine->delete();

        $purchaseOrder->calculateTotal();

        $vismaNetPurchaseOrderService = new VismaNetPurchaseOrderService();
        $response = $vismaNetPurchaseOrderService->updatePurchaseOrder($purchaseOrder);

        if (!$response['success']) {
            return [
                'success' => false,
                'error_message' => $response['message']
            ];
        }

        return [
            'success' => true,
            'error_message' => null
        ];
    }

    /**
     * @param PurchaseOrder $purchaseOrder
     * @return array
     */
    public function indelivery(PurchaseOrder $purchaseOrder): array
    {
        // Fetch the related sales order
        $salesOrder = $purchaseOrder->directOrder;
        if (!$salesOrder) {
            return [
                'success' => false,
                'error_message' => 'Could not find the related sales order.'
            ];
        }

        // First deliver the entire purchase order
        $purchaseOrderShipments = PurchaseOrderShipment::where('purchase_order_id', $purchaseOrder)->get();
        foreach ($purchaseOrderShipments as $purchaseOrderShipment) {
            $quantities = [];
            foreach ($purchaseOrderShipment->lines as $orderLine) {
                $quantities[$orderLine->id] = $orderLine->quantity;
            }

            $deliveryResponse = $this->deliverShipment($purchaseOrderShipment, $quantities);
            if (!$deliveryResponse['success']) {
                return $deliveryResponse;
            }
        }

        // Create a shipment for the sales order (if it does not already exist)
        $shipmentsQuery = Shipment::whereJsonContains('order_numbers', $salesOrder->order_number);
        $shipments = $shipmentsQuery->get();

        if ($shipments->count() === 0) {
            // Create a new shipment
            try {
                $vismaNetSalesOrderService = new VismaNetSalesOrderService();
                $vismaNetSalesOrderService->createShipment($salesOrder, ($purchaseOrder->is_direct ? true : false));
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error_message' => $e->getMessage()
                ];
            }

            // Sync shipments from Visma.net
            Process::timeout(300)->run('php artisan visma:fetch shipments');

            $shipments = $shipmentsQuery->get();
        }


        // Deliver the sales order shipment
        $vismaNetShipmentService = new VismaNetShipmentService();
        foreach ($shipments as $shipment) {
            $vismaNetShipmentService->completeShipment($shipment, ($purchaseOrder->is_direct ? true : false));
        }

        return [
            'success' => true,
            'error_message' => null
        ];
    }

    /**
     * @param PurchaseOrder $purchaseOrder
     * @return void
     */
    public static function setPurchaseOrderStatus(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->refresh();

        $providedShippingDetails = 1;
        $providedTrackingNumbers = 1;
        $uploadedInvoice = 1;

        foreach ($purchaseOrder->lines as $line) {
            if (!$line->promised_date) {
                // Missing shipping details
                $providedShippingDetails = 0;
            }

            if (!$line->tracking_number || !$line->is_shipped) {
                // Missing tracking number
                $providedTrackingNumbers = 0;
            }

            if (!$line->invoice_id) {
                // Missing invoice
                $uploadedInvoice = 0;
            }
        }

        $purchaseOrder->update([
            'status_shipping_details' => $providedShippingDetails,
            'status_tracking_number' => $providedTrackingNumbers,
            'status_invoice_uploaded' => $uploadedInvoice
        ]);
    }
}
