<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentInternalStatus;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use Illuminate\Http\Request;

class AppShipmentController extends Controller
{
    public function list(Request $request)
    {
        $query = Shipment::query();

        if ($request->has('status')) {
            //$query->where('status', $request->status);
            $query->orderBy('id', 'DESC')->limit(10);
        }

        $shipments = $query->with('address', 'lines')->get();

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

        return ApiResponseController::success($shipment->toArray());
    }

    public function pick(Request $request, Shipment $shipment)
    {
        $hasOverflow = false;

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

                if ($quantity > $shipmentLine->quantity) {
                    $hasOverflow = true;
                }
            }
        }

        // Update shipment status
        if ($hasOverflow) {
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
}
