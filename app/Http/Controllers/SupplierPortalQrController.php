<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Browsershot\Browsershot;

class SupplierPortalQrController extends Controller
{
    public function print(Request $request)
    {
        $qrCode = $this->getQrSvg($request);
        $metaData = $request->input('meta_data', []) ?: [];
        $qrType = 'DELIVERY';

        return view('supplierPortal.pages.qrCode.print', compact('qrCode', 'metaData', 'qrType'));
    }

    public function download(Request $request)
    {
        $qrCode = $this->getQrPng($request);
        $metaData = $request->input('meta_data', []) ?: [];
        $qrType = 'DELIVERY';

        $pdf = Pdf::loadView('supplierPortal.pages.qrCode.pdf', [
            'qrCode' => $qrCode,
            'metaData' => $metaData,
            'qrType' => $qrType,
        ])->setPaper('a4', 'portrait');

        $filename = 'qr-code-' . now()->format('Y-m-dH:i:s') . '.pdf';

        return $pdf->download($filename);
    }

    private function getQrSvg(Request $request): string
    {
        $data = $request->input('data', '');

        return QrCode::size(1000)
            ->backgroundColor(255, 255, 255)
            ->color(0, 0, 0)
            ->generate($data);
    }

    private function getQrPng(Request $request): string
    {
        $data = $request->input('data', '');

        return QrCode::format('png')
            ->size(650)
            ->margin(0)
            ->backgroundColor(255, 255, 255)
            ->color(0, 0, 0)
            ->generate($data);
    }
}
