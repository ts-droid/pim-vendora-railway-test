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
            $query->where('status', $request->status);
        }

        $shipments = $query->with('address', 'lines')->get();

        return ApiResponseController::success($shipments->toArray());
    }

    public function get(Shipment $shipment)
    {
        $shipment->load('address', 'lines');

        return ApiResponseController::success($shipment->toArray());
    }
}
