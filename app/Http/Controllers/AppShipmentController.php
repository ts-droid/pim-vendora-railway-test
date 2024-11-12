<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentInternalStatus;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Services\VismaNet\VismaNetShipmentService;
use Illuminate\Http\Request;

class AppShipmentController extends Controller
{
    public function list(Request $request)
    {
        $shipments = Shipment::where('status', 'Open')
            ->orderBy('id', 'DESC')->limit(10)
            ->with('address', 'lines')->get()
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

        // Load open siblings
        $shipment->openSiblings = Shipment::where('customer_number', '=', $shipment->customer_number)
            ->where('status', '=', 'Open')
            ->where('id', '!=', $shipment->id)
            ->load('address', 'lines', 'lines.article')
            ->get()
            ->toArray();

        return ApiResponseController::success($shipment->toArray());
    }

    public function ping(Request $request, Shipment $shipment)
    {
        if ($request->input('pingAll')) {
            Shipment::where('customer_number', '=', $shipment->customer_number)
                ->update(['ping_at' => time()]);
        }
        else {
            $shipment->update(['ping_at' => time()]);
        }

        return ApiResponseController::success();
    }

    public function pick(Request $request, Shipment $shipment)
    {
        $investigate = false;

        $lines = $request->input('lines');
        if ($lines && is_array($lines)) {
            foreach ($lines as $line) {
                $lineID = $line['id'] ?? 0;
                $quantity = $line['quantity'] ?? 0;

                $shipmentLine = ShipmentLine::where('shipment_id', '=', $shipment->id)
                    ->where('id', '=', $lineID)
                    ->first();

                if (!$shipmentLine) continue;

                $shipmentLine->update(['picked_quantity' => $quantity]);

                if ($quantity > $shipmentLine->quantity || $quantity == 0) {
                    $investigate = true;
                }
            }
        }

        // Update shipment status
        if ($investigate) {
            // Mark for investigation
            $shipment->update(['internal_status' => ShipmentInternalStatus::INVESTIGATE]);
        }
        else {
            // Mark as picked
            $shipment->update(['internal_status' => ShipmentInternalStatus::PICKED]);
        }

        $shipment->load('address', 'lines', 'lines.article');

        return ApiResponseController::success($shipment->toArray());
    }

    public function complete(Shipment $shipment)
    {
        return ApiResponseController::error('This feature is disabled in demo-mode.');

        // Complete the shipment in Visma.net
        $vismaNetShipmentService = new VismaNetShipmentService();
        $response = $vismaNetShipmentService->completeShipment($shipment);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        // Update internal status
        $shipment->update(['internal_status' => ShipmentInternalStatus::PACKED]);

        return ApiResponseController::success();
    }
}
