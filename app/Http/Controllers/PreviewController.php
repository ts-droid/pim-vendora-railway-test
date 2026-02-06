<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class PreviewController extends Controller
{
    public function receipt(SalesOrder $salesOrder)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        App::setLocale($salesOrder->language);

        $brandingData = $salesOrder->getBrandingDate();

        $pdf = Pdf::loadView('emails.salesOrder.receiptPdf', [
            'salesOrder' => $salesOrder,
            'brandingData' => $brandingData
        ])->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->stream();
    }

    public function salesOrderReceipt()
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

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
