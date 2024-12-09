<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentInternalStatus;
use App\Jobs\CompleteWgrOrder;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Services\VismaNet\VismaNetApiService;
use App\Services\VismaNet\VismaNetShipmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        }

        $shipment->is_backorder = $shipment->isBackorder();

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

    public function pick(Request $request, Shipment $shipment)
    {
        $displayName = (string) $request->header('display-name', '');

        $investigate = false;

        $lines = $request->input('lines');
        if (!is_array($lines)) {
            $lines = json_decode($lines, true);
        }

        if ($lines && is_array($lines)) {
            foreach ($lines as $line) {
                $lineID = $line['id'] ?? 0;
                $quantity = $line['quantity'] ?? 0;
                $investigationComment = $line['investigation_comment'] ?? '';
                $sound = $request->file('sound_' . $lineID); // TODO: Process and save sound

                $shipmentLine = ShipmentLine::where('shipment_id', '=', $shipment->id)
                    ->where('id', '=', $lineID)
                    ->first();

                if (!$shipmentLine) continue;

                $investigationSoundPath = $shipmentLine->investigation_sound_path;
                $investigationSoundUrl = $shipmentLine->investigation_sound_url;
                if ($sound) {
                    $filename = 'sound_' . $lineID . '.' . $sound->getClientOriginalExtension();
                    $investigationSoundPath = DoSpacesController::store('sounds/' . $filename, $sound->getContent(), true);
                    $investigationSoundUrl = DoSpacesController::getURL($investigationSoundPath);
                }

                $shipmentLine->update([
                    'picked_quantity' => $quantity,
                    'investigation_comment' => $investigationComment,
                    'investigation_sound_path' => $investigationSoundPath,
                    'investigation_sound_url' => $investigationSoundUrl
                ]);

                if ($quantity != $shipmentLine->shipped_quantity || $quantity == 0) {
                    $investigate = true;
                }
            }
        }

        // Update shipment status
        if ($investigate) {
            // Mark for investigation
            $shipment->update([
                'pick_signature' => $displayName,
                'internal_status' => ShipmentInternalStatus::INVESTIGATE,
                'ping_at' => 0
            ]);
        }
        else {
            // Mark as picked
            $shipment->update([
                'pick_signature' => $displayName,
                'internal_status' => ShipmentInternalStatus::PICKED,
                'ping_at' => 0
            ]);
        }

        $shipment->load('address', 'lines', 'lines.article');

        return ApiResponseController::success($shipment->toArray());
    }

    public function wgr(Shipment $shipment)
    {
        Log::channel('shipments')->info('Received request to re-send shipment to WGR.', ['shipmentNumber' => $shipment->number]);

        if (!$shipment->completed_at) {
            Log::channel('shipments')->info('Aborting because shipment is not completed.', ['shipmentNumber' => $shipment->number]);
            return ApiResponseController::error('Shipment it not completed.');
        }

        if (!$shipment->tracking_number) {
            Log::channel('shipments')->info('Aborting because tracking number is missing.', ['shipmentNumber' => $shipment->number]);
            return ApiResponseController::error('Tracking number is missing.');
        }

        if (!$shipment->order_numbers) {
            Log::channel('shipments')->info('Aborting because no linked order numbers.', ['shipmentNumber' => $shipment->number]);
            return ApiResponseController::error('No linked order numbers.');
        }

        foreach ($shipment->order_numbers as $orderNumber) {
            $wgrOrderID = SalesOrder::select('customer_ref_no')
                ->where('order_number', $orderNumber)
                ->pluck('customer_ref_no')
                ->first();

            if (!$wgrOrderID) {
                Log::channel('shipments')->info('Could not find WGR ID for order number (' . $orderNumber . ').', ['shipmentNumber' => $shipment->number]);
                continue;
            }

            CompleteWgrOrder::dispatch([
                'wgr_order_id' => $wgrOrderID,
                'tracking_number' => $shipment->tracking_number
            ])->onQueue('main');

            Log::channel('shipments')->info('Queued CompleteWgrOrder for shipment', ['shipmentNumber' => $shipment->number]);
        }

        return ApiResponseController::success();
    }

    public function complete(Request $request, Shipment $shipment)
    {
        Log::channel('shipments')->info('Received request to complete shipment', ['shipmentNumber' => $shipment->number]);

        $displayName = (string) $request->header('display-name', '');

        // Complete the shipment in Visma.net
        $vismaNetShipmentService = new VismaNetShipmentService();
        $response = $vismaNetShipmentService->completeShipment($shipment);

        if (!$response['success']) {
            Log::channel('shipments')->warning('Failed to complete shipment in Visma.net', ['shipmentNumber' => $shipment->number]);
            return ApiResponseController::error($response['message']);
        }

        Log::channel('shipments')->info('Completed shipment in Visma.net', ['shipmentNumber' => $shipment->number]);

        $trackingNumber = (string) $request->input('tracking_number', '');

        // Update internal status
        $shipment->update([
            'pack_signature' => $displayName,
            'tracking_number' => $trackingNumber,
            'internal_status' => ShipmentInternalStatus::PACKED,
            'completed_at' => date('Y-m-d H:i:s'),
            'ping_at' => 0
        ]);

        // Send tracking number to WGR
        if ($trackingNumber && $shipment->order_numbers) {
            foreach ($shipment->order_numbers as $orderNumber) {
                $wgrOrderID = SalesOrder::select('customer_ref_no')
                    ->where('order_number', $orderNumber)
                    ->pluck('customer_ref_no')
                    ->first();

                if ($wgrOrderID) {
                    CompleteWgrOrder::dispatch([
                        'wgr_order_id' => $wgrOrderID,
                        'tracking_number' => $trackingNumber
                    ])->onQueue('main');

                    Log::channel('shipments')->info('Queued CompleteWgrOrder for shipment', ['shipmentNumber' => $shipment->number]);
                }
                else {
                    Log::channel('shipments')->warning('Could not queue CompleteWgrOrder for shipment. Missing WGR Order ID.', ['shipmentNumber' => $shipment->number]);
                }
            }
        }
        else {
            Log::channel('shipments')->warning('Shipment has no tracking number or order numbers. (Tracking Number: {trackingNumber}) (Order numbers: {orderNumbersCount})', [
                'shipmentNumber' => $shipment->number,
                'trackingNumber' => $trackingNumber,
                'orderNumbersCount' => count($shipment->order_numbers)
            ]);
        }

        return ApiResponseController::success();
    }

    public function print(Shipment $shipment)
    {
        $vismaNetApi = new VismaNetApiService();

        $response = $vismaNetApi->callAPI('GET', '/v1/shipment/' . $shipment->number . '/printShipmentConfirmation', [], '', true);

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
