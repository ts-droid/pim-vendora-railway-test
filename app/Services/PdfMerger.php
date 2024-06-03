<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

class PdfMerger
{
    public function mergePdfs($pdfPath1, $pdfPath2, $outputPath)
    {
        $pdf = new Fpdi();

        // Create an instance of FPDI
        $pdf = new Fpdi();

        // Add pages from the first PDF
        $this->addPdfPages($pdf, $pdfPath1);

        // Add pages from the second PDF
        $this->addPdfPages($pdf, $pdfPath2);

        $pdf->Output('F', $outputPath);
    }

    private function addPdfPages(Fpdi $pdf, $filePath)
    {
        $pageCount = $pdf->setSourceFile($filePath);

        for ($pageNo = 1;$pageNo <= $pageCount;$pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }

        return $pageCount;
    }
}
