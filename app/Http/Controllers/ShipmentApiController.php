<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ShipmentApiController extends Controller
{
    public function receipt(Shipment $shipment)
    {
        $brandingData = $shipment->getBrandingData();

        $pdf = Pdf::loadView('pdf.salesOrderReceipt', compact('shipment', 'brandingData'));
        return $pdf->stream('receipt.pdf');
    }
}
