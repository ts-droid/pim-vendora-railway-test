<?php

namespace App\Http\Controllers;

use App\Actions\Mail\SendSalesOrderTrackingNumber;
use App\Enums\LaravelQueues;
use App\Enums\ShipmentInternalStatus;
use App\Jobs\CompleteWgrOrder;
use App\Jobs\SendSalesOrderReviewRequest;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Models\StockItem;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Services\VismaNet\VismaNetApiService;
use App\Services\VismaNet\VismaNetShipmentService;
use App\Services\WMS\StockItemService;
use App\Services\WMS\StockPlaceService;
use App\Utilities\EventLogger;
use App\Utilities\WarehouseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AppShipmentController extends Controller
{
    const GROUP_CUSTOMER_EXCLUDES = [
        '10460', // LSS Kund
        '10365', // Sample Order - Marketing
    ];

    public function list(Request $request)
    {
        $page = (int) $request->input('page', 0);
        $pageSize = (int) $request->input('page_size', 12);
        $status = (int) $request->input('status', -1);

        $shipmentsQuery = Shipment::where('status', 'Open')
            ->where('operation', 'Issue')
            ->orderBy('customer_number', 'ASC')
            ->orderBy('id', 'DESC');

        if ($page > 0) {
            $shipmentsQuery->offset(($page - 1) * $pageSize)->limit($pageSize);
        }
        if ($status != -1) {
            $shipmentsQuery->where('internal_status', $status);
        }

        $shipments = $shipmentsQuery->with('address', 'lines')
            ->get();

        foreach ($shipments as &$shipment) {
            $shipment->is_backorder = $shipment->isBackorder();
            $shipment->country_code = $shipment->getCountry();

            $shipment->has_notes = $shipment->note ? true : false;

            $salesOrders = SalesOrder::whereIn('order_number', $shipment->order_numbers)->get();
            if ($salesOrders) {
                foreach ($salesOrders as $salesOrder) {
                    if ($salesOrder->note || $salesOrder->internal_note || $salesOrder->store_note) {
                        $shipment->has_notes = true;
                    }
                }
            }
        }

        return ApiResponseController::success($shipments->toArray());
    }

    public function listTabCount()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $shipments = DB::table('shipments')
            ->select('internal_status', DB::raw('count(*) as total'))
            ->where('status', 'Open')
            ->where('operation', 'Issue')
            ->whereIn('internal_status', [0, 1, 3])
            ->groupBy('internal_status')
            ->get()
            ->keyBy('internal_status');

        return ApiResponseController::success([
            '0' => $shipments[0]->total ?? 0,
            '1' => $shipments[1]->total ?? 0,
            '3' => $shipments[3]->total ?? 0
        ]);
    }

    public function listHistory()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $shipments = Shipment::where('operation', 'Issue')
            ->where('internal_status', ShipmentInternalStatus::PACKED)
            ->where('completed_at', '>=', date('Y-m-d', strtotime('-2 month')))
            ->orderBy('completed_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->with('address', 'lines')
            ->get();

        foreach ($shipments as &$shipment) {
            $shipment->is_backorder = $shipment->isBackorder();

            $shipment->has_notes = $shipment->note ? true : false;

            $salesOrders = SalesOrder::whereIn('order_number', $shipment->order_numbers)->get();
            if ($salesOrders) {
                foreach ($salesOrders as $salesOrder) {
                    if ($salesOrder->note || $salesOrder->internal_note || $salesOrder->store_note) {
                        $shipment->has_notes = true;
                    }
                }
            }
        }

        return ApiResponseController::success($shipments->toArray());
    }

    public function get(Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $shipment->load('address', 'lines', 'lines.article');

        if ($shipment->address) {
            if ($shipment->address->country_code) {
                $shipment->address->country_name = get_country_name($shipment->address->country_code, 'en');
            } else {
                $shipment->address->country_name = '';
            }
        }

        foreach ($shipment->lines as &$line) {
            $line->order_quantity = $line->orderQuantity();

            $locations = WarehouseHelper::getArticleLocations($line->article_number, $line->shipped_quantity);

            $line->picking_locations = $locations['locations'];
            $line->picking_quantities = $locations['quantity'];
        }

        $shipment->is_backorder = $shipment->isBackorder();

        // Load order comments
        $salesOrders = SalesOrder::whereIn('order_number', $shipment->order_numbers)->get();

        $shipment->order_source = '';

        $orderNotes = [];
        foreach ($salesOrders as $salesOrder) {
            if (!$shipment->order_source) {
                $shipment->order_source = $salesOrder->source;
            }

            if ($salesOrder->note) {
                $orderNotes[] = (string) $salesOrder->note;
            }
            if ($salesOrder->internal_note) {
                $orderNotes[] = (string) $salesOrder->internal_note;
            }
            if ($salesOrder->store_note) {
                $orderNotes[] = (string) $salesOrder->store_note;
            }
        }

        $orderNotes = array_unique($orderNotes);
        $shipment->internal_note = implode((PHP_EOL . PHP_EOL), $orderNotes);
        $shipment->internal_note = preg_replace('/^\s*[\r\n]+|[\r\n]+\s*$/', '', $shipment->internal_note);

        // Load open siblings
        if (!in_array($shipment->customer_number, self::GROUP_CUSTOMER_EXCLUDES)) {
            $shipment->openSiblings = Shipment::where('customer_number', '=', $shipment->customer_number)
                ->where('status', '=', 'Open')
                ->where('internal_status', '=', ShipmentInternalStatus::OPEN)
                ->where('id', '!=', $shipment->id)
                ->with('address', 'lines', 'lines.article')
                ->get()
                ->toArray();
        }
        else {
            $shipment->openSiblings = [];
        }

        // Load customer ref numbers (WGR order ID's)
        $shipment->customer_ref_no = SalesOrder::select('customer_ref_no')
            ->whereIn('order_number', $shipment->order_numbers)
            ->pluck('customer_ref_no')
            ->filter();

        return ApiResponseController::success($shipment->toArray());
    }

    public function ping(Request $request, Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        if ($request->input('pingAll')) {
            Shipment::where('customer_number', '=', $shipment->customer_number)
                ->whereNotIn('customer_number', self::GROUP_CUSTOMER_EXCLUDES)
                ->where('status', '=', 'Open')
                ->update(['ping_at' => time()]);
        }
        else {
            $shipment->update(['ping_at' => time()]);
        }

        return ApiResponseController::success();
    }

    public function unping(Request $request, Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        if ($request->input('pingAll')) {
            Shipment::where('customer_number', '=', $shipment->customer_number)
                ->where('status', '=', 'Open')
                ->update(['ping_at' => 0]);
        }
        else {
            $shipment->update(['ping_at' => 0]);
        }

        return ApiResponseController::success();
    }

    public function checkSerialNumber(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $serialNumber = trim($request->input('serial_number'));
        $excludeLineID = (int) $request->input('exclude_line_id');

        if (!$serialNumber) {
            return ApiResponseController::error('Serial number must not be empty.');
        }

        $lines = DB::table('shipment_lines')
            ->select('serial_number')
            ->where('serial_number', 'LIKE', '%' . $serialNumber . '%')
            ->where('id', '!=', $excludeLineID)
            ->pluck('serial_number');

        if (!$lines || !$lines->count()) {
            return ApiResponseController::success();
        }

        foreach ($lines as $line) {
            $lineSerialNumbers = explode(',', $line);

            if (in_array($serialNumber, $lineSerialNumbers)) {
                return ApiResponseController::error('Serial number already in use.');
            }
        }

        return ApiResponseController::success();
    }

    public function pick(Request $request, Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $lockKey = 'shipment:pick:' . $shipment->id;
        $lock = Cache::lock($lockKey, 900); // 15 minutes lock

        if (!$lock->get()) {
            return ApiResponseController::error('Shipment is currently being picked by another user. Please try again later.');
        }

        try {
            $displayName = get_display_name();
            $stockItemService = new StockItemService();
            $stockPlaceService = new StockPlaceService();

            return DB::transaction(function () use ($request, $shipment, $displayName, $stockItemService, $stockPlaceService) {
                $lockedShipment = Shipment::whereKey($shipment->id)->lockForUpdate()->first();
                $lockedShipment->refresh();

                if ($lockedShipment->internal_status !== ShipmentInternalStatus::OPEN) {
                    return ApiResponseController::error('Shipment is not in a valid state for picking.');
                }

                $lines = $request->input('lines');
                if (!is_array($lines)) {
                    $lines = json_decode($lines, true);
                }

                if ($lines && is_array($lines)) {
                    foreach ($lines as $line) {
                        $lineID = $line['id'] ?? 0;
                        $quantity = $line['quantity'] ?? 0;
                        $pickingLocations = $line['picking_locations'] ?? ['--'];
                        $pickingLocationQuantity = $line['picking_location_quantity'] ?? [];

                        $serialNumbers = array_filter(array_map('trim', explode(',', $line['serial_numbers'] ?? '')));

                        $shipmentLine = ShipmentLine::where('shipment_id', $lockedShipment->id)
                            ->where('id', $lineID)
                            ->lockForUpdate()
                            ->first();

                        if (!$shipmentLine) {
                            continue;
                        }

                        $articleData = DB::table('articles')
                            ->select('serial_number_management')
                            ->where('article_number', '=', $shipmentLine->article_number)
                            ->first();

                        $serialNumberManagement = $articleData->serial_number_management ?? 0;
                        if ($serialNumberManagement && count($serialNumbers) !== $quantity) {
                            return ApiResponseController::error('Missing serial numbers for all articles (' . $shipmentLine->article_number . ').');
                        }

                        $shipmentLine->update([
                            'picked_quantity' => $quantity,
                            'serial_number' => $serialNumbers ? implode(',', $serialNumbers) : '',
                            'picking_location' => json_encode($pickingLocations),
                            'picking_location_quantity' => json_encode($pickingLocationQuantity),
                        ]);
                    }
                }

                $shipmentLines = ShipmentLine::where('shipment_id', $lockedShipment->id)->lockForUpdate()->get();
                $markForInvestigation = false;
                $utlevCompartment = $stockPlaceService->getCompartmentByIdentifier('UTLEV:1');

                if (!$utlevCompartment) {
                    return ApiResponseController::error('Destination compartment UTLEV:1 is missing.');
                }

                foreach ($shipmentLines as $shipmentLine) {
                    $pickingLocations = json_decode($shipmentLine->picking_location, true) ?: [];
                    $pickingLocationQuantities = json_decode($shipmentLine->picking_location_quantity, true) ?: [];

                    if ($shipmentLine->shipped_quantity != $shipmentLine->picked_quantity) {
                        $markForInvestigation = true;
                    }

                    foreach ($pickingLocations as $index => $identifier) {
                        if ($identifier === '--') {
                            continue;
                        }

                        $qty = (int) ($pickingLocationQuantities[$index] ?? 0);
                        if ($qty <= 0) {
                            continue;
                        }

                        $fromCompartment = $stockPlaceService->getCompartmentByIdentifier($identifier);
                        if (!$fromCompartment) {
                            return ApiResponseController::error('Invalid picking location: ' . $identifier);
                        }

                        $result = $stockItemService->moveStockItems(
                            $shipmentLine->article_number,
                            $qty,
                            $fromCompartment,
                            $utlevCompartment,
                            $displayName,
                            'Picked shipment #' . $lockedShipment->id,
                            false
                        );

                        EventLogger::logAction(
                            $displayName . ' picked ' . $qty . ' pcs of ' . $shipmentLine->article_number . ' for shipment ' . $lockedShipment->id . ', moving them from ' . $identifier . ' to UTLEV:1',
                            $displayName,
                            [
                                'article_number' => $shipmentLine->article_number,
                                'shipment_id' => $lockedShipment->id,
                                'from_compartment_id' => $fromCompartment->id,
                                'to_compartment_id' => $utlevCompartment->id
                            ]
                        );

                        if (!$result['success']) {
                            return ApiResponseController::error($result['message']);
                        }
                    }
                }

                $lockedShipment->update([
                    'pick_signature' => $displayName,
                    'internal_status' => $markForInvestigation ? ShipmentInternalStatus::INVESTIGATE : ShipmentInternalStatus::PICKED,
                    'ping_at' => 0,
                ]);

                if ($lockedShipment->order_numbers) {
                    foreach ($lockedShipment->order_numbers as $orderNumber) {
                        $salesOrder = SalesOrder::where('order_number', $orderNumber)->first();
                        if ($salesOrder) {
                            $salesOrder->update(['status_shipment_picked' => 1]);
                        }
                    }
                }

                $lockedShipment->load('address', 'lines', 'lines.article');

                return ApiResponseController::success($lockedShipment->toArray());
            });
        } finally {
            $lock->release();
        }
    }

    public function updateNote(Request $request, Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $shipment->update([
            'note' => (string) $request->input('note', ''),
        ]);

        return ApiResponseController::success();
    }

    public function update(Request $request, Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        Log::channel('shipments')->info('Received request to update shipment', ['shipmentNumber' => $shipment->number]);

        $trackingNumber = (string) $request->input('tracking_number', '');

        if (!$shipment->completed_at) {
            Log::channel('shipments')->info('Shipment is not completed. So can not update it.', ['shipmentNumber' => $shipment->number]);
            return ApiResponseController::error('Shipment is not completed. So can not update it.');
        }

        $shipment->update([
            'pack_signature' => get_display_name(),
            'tracking_number' => $trackingNumber,
            'ping_at' => 0
        ]);

        // Send tracking number to WGR
        $this->sendToWGR($shipment, $trackingNumber);

        return ApiResponseController::success();
    }

    public function updateLine(Request $request, Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $lineID = (int) $request->input('line_id');
        $quantity = (int) $request->input('quantity');
        $serialNumbers = (string) $request->input('serial_numbers');

        ShipmentLine::where('id', $lineID)
            ->where('shipment_id', $shipment->id)
            ->update([
                'picked_quantity' => $quantity,
                'serial_number' => $serialNumbers,
                'is_picked' => 1,
            ]);

        return ApiResponseController::success();
    }

    public function updateComment(Request $request, Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $lineID = (int) $request->input('line_id');
        $comment = (string) $request->input('comment');
        $sound = $request->file('sound');

        $shipmentLine = ShipmentLine::where('shipment_id', $shipment->id)
            ->where('id', $lineID)
            ->first();

        if (!$shipmentLine) {
            return ApiResponseController::error('Could not find shipment line.');
        }

        $investigationSoundPath = $shipmentLine->investigation_sound_path;
        $investigationSoundUrl = $shipmentLine->investigation_sound_url;

        if ($sound) {
            if ($investigationSoundPath) {
                DoSpacesController::delete($investigationSoundPath);
            }

            $filename = 'sound_' . $lineID . time() . '.' . $sound->getClientOriginalExtension();
            $investigationSoundPath = DoSpacesController::store('sounds/' . $filename, $sound->getContent(), true);
            $investigationSoundUrl = DoSpacesController::getURL($investigationSoundPath);
        }

        $shipmentLine->update([
            'investigation_comment' => $comment,
            'investigation_sound_path' => $investigationSoundPath,
            'investigation_sound_url' => $investigationSoundUrl
        ]);

        return ApiResponseController::success();
    }

    public function complete(Request $request, Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        Log::channel('shipments')->info('Received request to complete shipment', ['shipmentNumber' => $shipment->number]);

        // Complete the shipment in Visma.net
        $vismaNetShipmentService = new VismaNetShipmentService();
        $response = $vismaNetShipmentService->completeShipment($shipment);

        if (!$response['success']) {
            Log::channel('shipments')->warning('Failed to complete shipment in Visma.net', ['shipmentNumber' => $shipment->number]);
            return ApiResponseController::error($response['message']);
        }

        Log::channel('shipments')->info('Completed shipment in Visma.net', ['shipmentNumber' => $shipment->number]);

        $trackingNumber = (string) $request->input('tracking_number', '');
        $trackingNumberOld = $shipment->tracking_number;

        $displayName = get_display_name();

        // Update internal status
        $shipment->update([
            'pack_signature' => $displayName,
            'tracking_number' => $trackingNumber,
            'internal_status' => ShipmentInternalStatus::PACKED,
            'completed_at' => date('Y-m-d H:i:s'),
            'ping_at' => 0
        ]);

        // Remove stock items from "UTLEV"
        $stockPlaceService = new StockPlaceService();
        $stockItemService = new StockItemService();

        $compartment = $stockPlaceService->getCompartmentByIdentifier('UTLEV:1');

        foreach ($shipment->lines as $line) {
            $stockItems = $stockItemService->getStockItemsFromCompartment(
                $compartment,
                $line->article_number,
                $line->quantity,
            );

            $response = $stockItemService->removeStockItems(
                $stockItems,
                $displayName,
                'Completing shipment #' . $shipment->id,
                false
            );


            // Group stock items before logging
            $groupedStockItems = [];
            foreach ($stockItems as $stockItem) {
                $key = $stockItem->article_number . '_:_' . $stockItem->stock_place_compartment_id;

                if (!isset($groupedStockItems[$key])) {
                    $stockPlaceCompartment = StockPlaceCompartment::find($stockItem->stock_place_compartment_id);
                    $identifier = ($stockPlaceCompartment->stockPlace->identifier ?? '') . ':' . ($stockPlaceCompartment->identifier ?? '');

                    $groupedStockItems[$key] = [
                        'article_number' => $stockItem->article_number,
                        'stock_place_compartment_id' => $stockPlaceCompartment->id,
                        'identifier' => $identifier,
                        'qty' => 0
                    ];
                }

                $groupedStockItems[$key]++;
            }

            foreach ($groupedStockItems as $item) {
                EventLogger::logAction(
                    $displayName . ' completed shipment ' . $shipment->id . ', removing ' . $item['qty'] . ' pcs of ' . $item['article_number'] . ' from ' . $item['identifier'],
                    $displayName,
                    [
                        'article_number' => $item['article_number'],
                        'shipment_id' => $shipment->id,
                        'from_compartment_id' => $item['stock_place_compartment_id']
                    ]
                );
            }

            if (!$response['success']) {
                Log::channel('shipments')->error('Failed to remove stock items when competing shipment.', ['shipmentNumber' => $shipment->number]);
            }
        }


        // Send/notify tracking number to customer
        if ($trackingNumber != $trackingNumberOld || !$trackingNumberOld) {
            $this->notifyTrackingNumber($shipment, $trackingNumber);
        }

        // Update order status
        if ($shipment->order_numbers) {
            foreach ($shipment->order_numbers as $orderNumber) {
                $salesOrder = SalesOrder::where('order_number', '=', $orderNumber)->first();

                if (!$salesOrder) continue;

                $salesOrder->update(['status_shipment_sent' => 1,]);
            }
        }

        return ApiResponseController::success();
    }

    public function notifyTrackingNumber(Shipment $shipment, $trackingNumber)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        if (!$shipment->order_numbers) {
            Log::channel('shipments')->warning('Shipment has no order numbers. (Order numbers: {orderNumbersCount})', [
                'shipmentNumber' => $shipment->number,
                'orderNumbersCount' => count($shipment->order_numbers)
            ]);

            return;
        }

        foreach ($shipment->order_numbers as $orderNumber) {
            $salesOrder = SalesOrder::where('order_number', $orderNumber)->first();
            if (!$salesOrder) {
                Log::channel('shipments')->warning('Failed to notify tracking number for shipment {shipmentNumber}. Order number {orderNumber} not found.', [
                    'shipmentNumber' => $shipment->number,
                    'orderNumber' => $orderNumber,
                ]);
                continue;
            }

            if ($salesOrder->customer_ref_no) {
                // Notify through WGR
                CompleteWgrOrder::dispatch([
                    'wgr_order_id' => $salesOrder->customer_ref_no,
                    'tracking_number' => $trackingNumber
                ])->onQueue('main');

                Log::channel('shipments')->info('Queued CompleteWgrOrder for shipment', ['shipmentNumber' => $shipment->number]);
            }
            elseif ($trackingNumber && $salesOrder->email && filter_var($salesOrder->email, FILTER_VALIDATE_EMAIL)) {
                // Use our own notification
                (new SendSalesOrderTrackingNumber)->execute($salesOrder, $trackingNumber);

                Log::channel('shipments')->info('Queued tracking number email for shipment {shipmentNumber}.', ['shipmentNumber' => $shipment->number]);
            }

            // Queue job to send review request
            if ($salesOrder->isBrandPageOrder()) {
                SendSalesOrderReviewRequest::dispatch($salesOrder)->delay(now()->addDays(7));
            }
        }
    }

    public function print(Shipment $shipment)
    {
        $vismaNetApi = new VismaNetApiService();

        $response = $vismaNetApi->callAPI('GET', '/v1/shipment/' . $shipment->number . '/printShipmentConfirmation', [], '', true, true);

        $data = $response['response'];

        return response($data, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="shipment-confirmation.pdf"'
        ]);
    }

    public function clearVisma(Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $vismaNetShipmentService = new VismaNetShipmentService();
        $response = $vismaNetShipmentService->deleteIfDeleted($shipment);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        return ApiResponseController::success();
    }
}
