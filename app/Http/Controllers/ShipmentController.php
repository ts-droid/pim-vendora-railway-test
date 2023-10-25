<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function getVisma(Request $request)
    {
        $shipmentNumber = $request->get('shipment_number');

        if (!$shipmentNumber) {
            return ApiResponseController::error('Missing parameter "shipment_number".');
        }

        $vismaNetController = new VismaNetController();
        $shipment = $vismaNetController->getShipment($shipmentNumber);

        return ApiResponseController::success($shipment);
    }
}
