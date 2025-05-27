<?php

namespace App\Http\Controllers;

use App\Actions\Mail\SendSalesOrderTrackingNumber;
use App\Enums\LaravelQueues;
use App\Enums\ShipmentInternalStatus;
use App\Jobs\CompleteWgrOrder;
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
use App\Utilities\WarehouseHelper;
use Illuminate\Http\Request;
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
        $shipments = Shipment::where('status', 'Open')
            ->where('operation', 'Issue')
            ->orderBy('id', 'DESC')
            ->with('address', 'lines')
            ->get();

        foreach ($shipments as &$shipment) {
            $shipment->is_backorder = $shipment->isBackorder();
        }

        return ApiResponseController::success($shipments->toArray());
    }

    public function listHistory()
    {
        $shipments = Shipment::where('operation', 'Issue')
            ->where('internal_status', ShipmentInternalStatus::PACKED)
            ->where('completed_at', '>=', date('Y-m-d', strtotime('-2 month')))
            ->orderBy('completed_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->with('address', 'lines')
            ->get();

        foreach ($shipments as &$shipment) {
            $shipment->is_backorder = $shipment->isBackorder();
        }

        return ApiResponseController::success($shipments->toArray());
    }

    public function get(Shipment $shipment)
    {
        $shipment->load('address', 'lines', 'lines.article');

        foreach ($shipment->lines as &$line) {
            $line->order_quantity = $line->orderQuantity();

            $locations = WarehouseHelper::getArticleLocations($line->article_number, $line->shipped_quantity);

            $this->picking_locations = $locations['locations'];
            $this->picking_quantities = $locations['quantity'];
        }

        $shipment->is_backorder = $shipment->isBackorder();

        // Load order comments
        $salesOrders = SalesOrder::whereIn('order_number', $shipment->order_numbers)->get();

        $orderNotes = [];
        foreach ($salesOrders as $salesOrder) {
            if (!$salesOrder->internal_note) continue;

            $orderNotes[] = (string) $salesOrder->internal_note;
        }

        $shipment->internal_note = implode((PHP_EOL . PHP_EOL), $orderNotes);

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
        $displayName = get_display_name();

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

                $serialNumbers = $line['serial_numbers'] ?? '';
                $serialNumbers = explode(',', $serialNumbers);
                $serialNumbers = array_map('trim', $serialNumbers);
                $serialNumbers = array_filter($serialNumbers);

                $shipmentLine = ShipmentLine::where('shipment_id', '=', $shipment->id)
                    ->where('id', '=', $lineID)
                    ->first();

                if (!$shipmentLine) continue;

                $articleData = DB::table('articles')
                    ->select('serial_number_management')
                    ->where('article_number', '=', $shipmentLine->article_number)
                    ->first();

                $serialNumberManagement = $articleData->serial_number_management ?? 0;

                if ($serialNumberManagement && count($serialNumbers) != $quantity) {
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

        $stockItemService = new StockItemService();
        $stockPlaceService = new StockPlaceService();

        // Get fresh pair of shipment lines
        $shipmentLines = ShipmentLine::where('shipment_id', '=', $shipment->id)->get();

        $markForInvestigation = false;

        if ($shipmentLines) {
            foreach ($shipmentLines as $shipmentLine) {
                $pickingLocations = json_decode($shipmentLine->picking_location, true);
                $pickingLocationQuantity = json_decode($shipmentLine->picking_location_quantity, true);

                if ($shipmentLine->shipped_quantity != $shipmentLine->picked_quantity) {
                    $markForInvestigation = true;
                }

                for ($i = 0;$i < count($pickingLocations);$i++) {
                    $pickingLocation = $pickingLocations[$i];
                    $pickingLocationQuantity = $pickingLocationQuantity[$i] ?? 0;

                    if ($pickingLocation == '--') {
                        continue;
                    }

                    $compartment = $stockPlaceService->getCompartmentByIdentifier($pickingLocation);

                    $stockItems = $stockItemService->getStockItemsFromCompartment($compartment, $shipmentLine->article_number, $pickingLocationQuantity);
                    if ($stockItems->count() == 0) {
                        continue;
                    }

                    $stockItemService->removeStockItems($stockItems, $displayName);
                }
            }
        }

        // Mark as picked
        $shipment->update([
            'pick_signature' => $displayName,
            'internal_status' => $markForInvestigation ? ShipmentInternalStatus::INVESTIGATE : ShipmentInternalStatus::PICKED,
            'ping_at' => 0
        ]);

        $shipment->load('address', 'lines', 'lines.article');

        return ApiResponseController::success($shipment->toArray());
    }

    public function update(Request $request, Shipment $shipment)
    {
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

        // Update internal status
        $shipment->update([
            'pack_signature' => get_display_name(),
            'tracking_number' => $trackingNumber,
            'internal_status' => ShipmentInternalStatus::PACKED,
            'completed_at' => date('Y-m-d H:i:s'),
            'ping_at' => 0
        ]);

        // Send/notify tracking number to customer
        if ($trackingNumber != $trackingNumberOld) {
            $this->notifyTrackingNumber($shipment, $trackingNumber);
        }

        return ApiResponseController::success();
    }

    public function notifyTrackingNumber(Shipment $shipment, $trackingNumber)
    {
        if (!$trackingNumber || !$shipment->order_numbers) {
            Log::channel('shipments')->warning('Shipment has no tracking number or order numbers. (Tracking Number: {trackingNumber}) (Order numbers: {orderNumbersCount})', [
                'shipmentNumber' => $shipment->number,
                'trackingNumber' => $trackingNumber,
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
            elseif ($salesOrder->email && filter_var($salesOrder->email, FILTER_VALIDATE_EMAIL)) {
                // Use our own notification
                (new SendSalesOrderTrackingNumber)->execute($salesOrder, $trackingNumber);

                Log::channel('shipments')->info('Queued tracking number email for shipment {shipmentNumber}.', ['shipmentNumber' => $shipment->number]);
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
        $vismaNetShipmentService = new VismaNetShipmentService();
        $response = $vismaNetShipmentService->deleteIfDeleted($shipment);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        return ApiResponseController::success();
    }
}
