<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
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
}
