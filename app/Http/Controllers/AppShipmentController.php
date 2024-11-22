<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentInternalStatus;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Services\VismaNet\VismaNetApiService;
use App\Services\VismaNet\VismaNetShipmentService;
use Illuminate\Http\Request;

class AppShipmentController extends Controller
{
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
        $shipment->openSiblings = Shipment::where('customer_number', '=', $shipment->customer_number)
            ->where('status', '=', 'Open')
            ->where('internal_status', '=', ShipmentInternalStatus::OPEN)
            ->where('id', '!=', $shipment->id)
            ->with('address', 'lines', 'lines.article')
            ->get()
            ->toArray();

        return ApiResponseController::success($shipment->toArray());
    }

    public function ping(Request $request, Shipment $shipment)
    {
        if ($request->input('pingAll')) {
            Shipment::where('customer_number', '=', $shipment->customer_number)
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
                'internal_status' => ShipmentInternalStatus::INVESTIGATE,
                'ping_at' => 0
            ]);
        }
        else {
            // Mark as picked
            $shipment->update([
                'internal_status' => ShipmentInternalStatus::PICKED,
                'ping_at' => 0
            ]);
        }

        $shipment->load('address', 'lines', 'lines.article');

        return ApiResponseController::success($shipment->toArray());
    }

    public function complete(Request $request, Shipment $shipment)
    {
        // Complete the shipment in Visma.net
        $vismaNetShipmentService = new VismaNetShipmentService();
        $response = $vismaNetShipmentService->completeShipment($shipment);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        $trackingNumber = (string) $request->input('tracking_number', '');

        // Update internal status
        $shipment->update([
            'tracking_number' => $trackingNumber,
            'internal_status' => ShipmentInternalStatus::PACKED,
            'ping_at' => 0
        ]);

        // TODO: Send tracking number to WGR???

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
