<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class PdfGenerator
{
    public function generateFromView(string $view, array $data)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $pdf = Pdf::loadView($view, $data);

        return $pdf->output();
    }
}
