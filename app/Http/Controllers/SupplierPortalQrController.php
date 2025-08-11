<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SupplierPortalQrController extends Controller
{
    public function copy(Request $request)
    {
        $qrCode = $this->getQrPng($request);

        return response($qrCode, 200)->header('Content-Type', 'image/png');
    }

    public function print(Request $request)
    {
        $qrCode = $this->getQrSvg($request);

        return view('supplierPortal.pages.qrCode.print', compact('qrCode'));
    }

    public function download(Request $request)
    {
        $qrCode = $this->getQrPng($request);

        $pdf = Pdf::loadView('supplierPortal.pages.qrCode.pdf', [
            'qrCode' => $qrCode,
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
