<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ShipmentApiController extends Controller
{
    public function receipt(Shipment $shipment)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $brandingData = $shipment->getBrandingData();

        App::setLocale($brandingData['language_code'] ?: 'en');

        $pdf = Pdf::loadView('pdf.salesOrderReceipt', compact('shipment', 'brandingData'));
        return $pdf->stream('receipt.pdf');
    }
}
