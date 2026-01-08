<?php

namespace App\Services;

use App\Http\Controllers\DoSpacesController;
use App\Models\CanceledPurchaseOrderLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderException;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use App\Models\Shipment;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Services\VismaNet\VismaNetPurchaseOrderService;
use App\Services\VismaNet\VismaNetSalesOrderService;
use App\Services\VismaNet\VismaNetShipmentService;
use App\Services\WMS\StockItemService;
use App\Services\WMS\StockPlaceService;
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
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

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
        $updateResponse = $vismaNetPurchaseOrderService->updatePurchaseOrder($purchaseOrder, null, false);

        if (!$updateResponse['success']) {
            return [
                'success' => false,
                'old_line' => $purchaseOrderLine,
                'new_line' => $newLine,
                'error_message' => $updateResponse['message'],
                'meta' => $updateResponse['meta'] ?? null
            ];
        }

        return [
            'success' => true,
            'old_line' => $purchaseOrderLine,
            'new_line' => $newLine,
            'error_message' => null
        ];
    }

    public function releasePurchaseOrderShipments(): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $shipmentIDs = DB::table('purchase_order_shipment_queue')->pluck('purchase_order_shipment_id');

        $vismaNetPurchaseOrderService = new VismaNetPurchaseOrderService();
        $stockPlaceService = new StockPlaceService();
        $stockItemService = new StockItemService();

        foreach ($shipmentIDs as $shipmentID) {
            $purchaseOrderShipment = PurchaseOrderShipment::find($shipmentID);
            if (!$purchaseOrderShipment) continue;

            $response = $vismaNetPurchaseOrderService->createPurchaseOrderReceipt(
                $purchaseOrderShipment->purchaseOrder,
                $purchaseOrderShipment,
                $purchaseOrderShipment->comment
            );

            if (!$response['success']) {
                NotificationService::sendMail(
                    'Failed to create purchase order receipt',
                    'Failed to create purchase order receipt for shipment "' . $purchaseOrderShipment->id . '" in Visma.net. Response: ' . json_encode($response)
                );
                continue;
            }

            $receiptNumber = $response['receiptNumber'];
            if ($receiptNumber) {
                $vismaNetPurchaseOrderService->releasePurchaseOrderReceipt($receiptNumber);
            }


            // Find or create stock place "INLEV"
            $stockPlace = StockPlace::where('identifier', 'INLEV')->first();
            if (!$stockPlace) {
                $response = $stockPlaceService->createStockPlace([
                    'identifier' => 'INLEV',
                    'map_position_x' => 0,
                    'map_position_y' => 0,
                    'map_size_x' => 1,
                    'map_size_y' => 1,
                    'type' => 0,
                    'is_active' => 1,
                    'is_temporary' => 1,
                    'is_virtual' => 1,
                ]);

                if (!$response['success']) {
                    NotificationService::sendMail(
                        'Failed to create INLEV',
                        'Failed to create the stock place INLEV when running releasePurchaseOrderShipments() for shipment "' . $purchaseOrderShipment->id . '".'
                    );
                    continue;
                }

                $stockPlace = $response['stockPlace'];
            }

            // Create a new compartment
            $indeliveryCompartment = null;

            for ($i = 1;$i < 5000;$i++) {
                $exists = StockPlaceCompartment::where('stock_place_id', $stockPlace->id)
                    ->where('identifier', $i)
                    ->exists();

                if ($exists) continue;

                $indeliveryCompartment = StockPlaceCompartment::create([
                    'identifier' => $i,
                    'stock_place_id' => $stockPlace->id,
                    'volume_class' => 'A',
                    'width' => 100,
                    'height' => 100,
                    'depth' => 100,
                    'is_manual' => 1,
                ]);
                break;
            }

            if (!$indeliveryCompartment) {
                NotificationService::sendMail(
                    'Failed to create INLEV compartment',
                    'Failed to create the compartment for INLEV when running releasePurchaseOrderShipments() for shipment "' . $purchaseOrderShipment->id . '".'
                );
                continue;
            }

            $lines = DB::table('purchase_order_shipment_lines')->where('purchase_order_shipment_id', $purchaseOrderShipment->id)->get();
            foreach ($lines as $line) {
                $purchaseOrderLine = PurchaseOrderLine::find($line->purchase_order_line_id);
                if (!$purchaseOrderLine) continue;

                $stockItemService->addStockItem(
                    $purchaseOrderLine->article_number,
                    $line->quantity,
                    $indeliveryCompartment,
                    $purchaseOrderShipment->completed_by,
                    'Shipment delivery PO ' . $purchaseOrderShipment->purchaseOrder->id,
                );

                DB::table('articles')
                    ->where('article_number', $purchaseOrderLine->article_number)
                    ->increment('stock_manageable', $line->quantity);
            }
        }

        // Clear the queue
        DB::table('purchase_order_shipment_queue')->truncate();
    }

    /**
     * @param PurchaseOrderShipment $purchaseOrderShipment
     * @param array $quantities
     * @return array
     */
    public function deliverShipment(PurchaseOrderShipment $purchaseOrderShipment, array $quantities, string $comment = '', array $exceptions = [], array $images = []): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $purchaseOrderShipment->refresh();

        if (!$quantities) {
            return [
                'success' => false,
                'error_message' => 'No quantities provided.'
            ];
        }

        $lineIDs = DB::table('purchase_order_shipment_lines')
            ->where('purchase_order_shipment_id', $purchaseOrderShipment->id)
            ->pluck('purchase_order_line_id');

        // Make sure the purchase order is not parked/on-hold
        $vismaNetPurchaseOrderService = new VismaNetPurchaseOrderService();
        $unparkResponse = $vismaNetPurchaseOrderService->unparkPurchaseOrder($purchaseOrderShipment->purchaseOrder);

        if (!$unparkResponse['success']) {
            return [
                'success' => false,
                'error_message' => 'Failed to unpark purchase order. (' . $unparkResponse['error'] . ')',
            ];
        }

        DB::beginTransaction();

        foreach ($lineIDs as $lineID) {
            $qty = (int) ($quantities[$lineID] ?? 0);
            $qty = max(0, $qty);

            $exception = $exceptions[$lineID] ?? null;
            $exceptionImages = $images[$lineID] ?? [];

            $orderLine = PurchaseOrderLine::find($lineID);
            $qtyOnShipment = DB::table('purchase_order_shipment_lines')
                ->where('purchase_order_shipment_id', $purchaseOrderShipment->id)
                ->where('purchase_order_line_id', $orderLine->id)
                ->sum('quantity');

            if ($qty != $qtyOnShipment && !$exception) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error_message' => 'You must provide an exception type for all lines not matching the quantity.'
                ];
            }

            if ($qty != $qtyOnShipment) {
                $diffQty = $qty - $qtyOnShipment;
                $qty = min($qty, $qtyOnShipment);
            } else {
                $diffQty = 0;
            }

            if ($diffQty !== 0) {
                // Upload images
                $images = [];

                foreach ($exceptionImages as $image) {
                    $images[] = DoSpacesController::store(
                        time() . rand(0, 999_999_999) . '_' . $image->getClientOriginalName(),
                        $image->getContent(),
                        false
                    );
                }

                // Create Exception
                PurchaseOrderException::create([
                    'purchase_order_shipment_id' => $purchaseOrderShipment->id,
                    'purchase_order_line_id' => $orderLine->id,
                    'diff' => $diffQty,
                    'exception_type' => $exception,
                    'images' => $images
                ]);
            }

            if ($qty == 0) {
                // Disconnect the line from the shipment
                DB::table('purchase_order_shipment_lines')
                    ->where('purchase_order_shipment_id', $purchaseOrderShipment->id)
                    ->where('purchase_order_line_id', $lineID)
                    ->delete();

                $orderLine->update([
                    'is_shipped' => 0,
                    'purchase_order_shipment_id' => 0
                ]);

                continue;
            }

            // Update the quantity on the shipment
            DB::table('purchase_order_shipment_lines')
                ->where('purchase_order_shipment_id', $purchaseOrderShipment->id)
                ->where('purchase_order_line_id', $lineID)
                ->update(['quantity' => $qty]);

            $quantityReceived = $orderLine->quantity_received + $qty;

            $orderLine->update([
                'is_completed' => ($quantityReceived == $orderLine->quantity) ? 1 : 0,
                'quantity_received' => $quantityReceived,
                'app_state_quantity' => null,
                'app_state_verified' => 0
            ]);
        }

        // Update the purchase order
        $quantityOpen = (int) PurchaseOrderLine::select(DB::raw('SUM(quantity - quantity_received) AS quantity_open'))
            ->where('purchase_order_id', $purchaseOrderShipment->purchaseOrder->id)
            ->first()->quantity_open ?: 0;

        $purchaseOrderShipment->purchaseOrder->update([
            'status_received' => ($quantityOpen === 0) ? 1 : 0
        ]);

        // Update the shipment
        $purchaseOrderShipment->update([
            'comment' => $comment,
            'is_completed' => 1,
            'completed_at' => now(),
            'completed_by' => get_display_name() ?: 'Unknown',
        ]);

        // Add to "purchase_order_shipment_queue"
        DB::table('purchase_order_shipment_queue')->insert([
            'purchase_order_shipment_id' => $purchaseOrderShipment->id
        ]);


        DB::commit();

        return [
            'success' => true,
            'error_message' => null
        ];
    }

    public function createEmptyShipment(PurchaseOrder $purchaseOrder): PurchaseOrderShipment
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        return PurchaseOrderShipment::create([
            'purchase_order_id' => $purchaseOrder->id,
            'receipt' => '',
            'tracking_number' => '',
        ]);
    }

    /**
     * @param PurchaseOrder $purchaseOrder
     * @param array $data
     * @param PurchaseOrderLine[] $lines
     * @return PurchaseOrderShipment
     */
    public function createShipment(PurchaseOrder $purchaseOrder, array $data = [], mixed $lines = [], array $quantities = []): PurchaseOrderShipment
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $purchaseOrder->refresh();

        $receipt = $data['receipt'] ?? null;
        $trackingNumber = $data['tracking_number'] ?? null;

        $emptyShipment = $purchaseOrder->getEmptyShipment();
        if ($emptyShipment) {
            // Use the empty shipment
            $emptyShipment->update([
                'receipt' => $receipt,
                'tracking_number' => $trackingNumber,
            ]);

            $shipment = $emptyShipment;
        } else {
            // Create a new shipment
            $shipment = PurchaseOrderShipment::create([
                'purchase_order_id' => $purchaseOrder->id,
                'receipt' => $receipt,
                'tracking_number' => $trackingNumber,
            ]);
        }

        foreach ($lines as $line) {
            $quantity = $quantities[$line->id] ?? $line->quantity;

            DB::table('purchase_order_shipment_lines')->insert([
                'purchase_order_shipment_id' => $shipment->id,
                'purchase_order_line_id' => $line->id,
                'quantity' => $quantity
            ]);

            $line->update([
                'is_shipped' => 1,
                'tracking_number' => $trackingNumber,
                'purchase_order_shipment_id' => $shipment->id
            ]);
        }

        self::setPurchaseOrderStatus($purchaseOrder);

        return $shipment;
    }

    /**
     * @param PurchaseOrder $purchaseOrder
     * @return array
     */
    public function cancelPurchaseOrder(PurchaseOrder $purchaseOrder): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // OBS! Visma.net API does not support deleting the whole order, so we have to delete each line separately

        // Delete all local order lines
        PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)->delete();

        // Sync order to delete lines in Visma.net
        $vismaNetPurchaseOrderService = new VismaNetPurchaseOrderService();
        $response = $vismaNetPurchaseOrderService->updatePurchaseOrder($purchaseOrder);
        if (!$response['success']) {
            return [
                'success' => false,
                'error_message' => $response['message']
            ];
        }

        // Send email to supplier that order was cancelled
        $mailer = new PurchaseOrderEmailer();
        $mailer->sendCancelOrder($purchaseOrder);

        // Delete the local order (it will be synced later again)
        $purchaseOrder->delete();

        return [
            'success' => true,
            'error_message' => null
        ];
    }

    public function cancelRow(int $lineID): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $purchaseOrderLine = PurchaseOrderLine::find($lineID);
        if (!$purchaseOrderLine) {
            return [
                'success' => false,
                'error_message' => 'Could not find the order line.'
            ];
        }

        $purchaseOrder = $purchaseOrderLine->purchaseOrder;

        // Create a cancelled line record
        CanceledPurchaseOrderLine::create([
            'purchase_order_id' => $purchaseOrderLine->purchase_order_id,
            'article_number' => $purchaseOrderLine->article_number,
            'description' => $purchaseOrderLine->description,
            'unit_price' => (float) $purchaseOrderLine->unit_cost,
            'quantity' => (int) $purchaseOrderLine->quantity,
        ]);

        $purchaseOrderLineCopy = $purchaseOrderLine->replicate();
        $purchaseOrderLine->delete();

        $purchaseOrder->calculateTotal();

        $vismaNetPurchaseOrderService = new VismaNetPurchaseOrderService();
        $response = $vismaNetPurchaseOrderService->updatePurchaseOrder($purchaseOrder);

        // Send email to supplier that order line was cancelled
        $mailer = new PurchaseOrderEmailer();
        $mailer->sendCancelRow($purchaseOrder, $purchaseOrderLineCopy);

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
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Fetch the related sales order
        $salesOrder = $purchaseOrder->directOrder;
        if (!$salesOrder) {
            return [
                'success' => false,
                'error_message' => 'Could not find the related sales order.'
            ];
        }

        // First deliver the entire purchase order
        $purchaseOrderShipments = PurchaseOrderShipment::where('purchase_order_id', $purchaseOrder->id)->get();
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

    public function autoDeliverPurchaseOrders(): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $purchaseOrders = PurchaseOrder::where('status', '!=', 'Closed')->get();

        foreach ($purchaseOrders as $purchaseOrder) {
            $shipmentIDs = PurchaseOrderShipment::where('purchase_order_id', $purchaseOrder->id)->pluck('id')->toArray();

            $deliveredQuantities = [];

            foreach ($purchaseOrder->lines as $line) {
                $assignedQuantity = (int) DB::table('purchase_order_shipment_lines')
                    ->whereIn('purchase_order_shipment_id', $shipmentIDs)
                    ->where('purchase_order_line_id', $line->id)
                    ->sum('quantity');

                if ($line->quantity_received <= $assignedQuantity) continue;

                $deliveredQuantities[$line->id] = $line->quantity_received - $assignedQuantity;
            }

            if (empty($deliveredQuantities)) continue;

            // Create shipment and shipment lines
            $shipment = PurchaseOrderShipment::create([
                'purchase_order_id' => $purchaseOrder->id,
                'is_completed' => 1,
                'completed_at' => now(),
                'completed_by' => 'System'
            ]);

            foreach ($deliveredQuantities as $lineID => $qty) {
                DB::table('purchase_order_shipment_lines')->insert([
                    'purchase_order_shipment_id' => $shipment->id,
                    'purchase_order_line_id' => $lineID,
                    'quantity' => $qty
                ]);
            }
        }

    }

    /**
     * @param PurchaseOrder $purchaseOrder
     * @return void
     */
    public static function setPurchaseOrderStatus(PurchaseOrder $purchaseOrder)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service static method.', $__serviceLogContext);

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
