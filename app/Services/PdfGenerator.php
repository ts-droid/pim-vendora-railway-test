<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class PdfGenerator
{
    public function generateFromView(string $view, array $data)
    {
        $pdf = Pdf::loadView($view, $data);

        return $pdf->output();
    }
}
