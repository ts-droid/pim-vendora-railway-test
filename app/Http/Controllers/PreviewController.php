<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class PreviewController extends Controller
{
    public function salesOrderReceipt()
    {
        $shipment = new Shipment();

        $brandingData = [
            'brand_name' => 'Vendora Nordic AB',
            'logo_url' => asset('/assets/img/logos/logo_vendora.png'),
            'logo_path' => public_path('/assets/img/logos/logo_vendora.png'),
            'language_code' => 'en'
        ];

        App::setLocale('sv');

        $pdf = Pdf::loadView('pdf.salesOrderReceipt', compact('shipment', 'brandingData'));
        return $pdf->stream('receipt.pdf');
    }
}
