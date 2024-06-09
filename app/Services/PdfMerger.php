<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader;

class PdfMerger
{
    public function mergePdfs($pdfPath1, $pdfPath2, $outputPath)
    {
        // Create an instance of FPDI
        $pdf = new Fpdi();

        // Add pages from the first PDF
        $this->addPdfPages($pdf, $pdfPath1);

        // Add pages from the second PDF
        $this->addPdfPages($pdf, $pdfPath2);

        $pdf->Output('F', $outputPath);
    }

    public function mergePdfContents($pdfContent1, $pdfContent2)
    {
        $pdf = new Fpdi();

        // Add pages from the first PDF content
        $this->addPdfPagesFromContent($pdf, $pdfContent1);

        // Add pages from the second PDF content
        $this->addPdfPagesFromContent($pdf, $pdfContent2);

        return $pdf->Output('S');
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

    private function addPdfPagesFromContent(Fpdi $pdf, $pdfContent)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $pdfContent);
        rewind($stream);

        $pdfReader = StreamReader::createByString($pdfContent);
        $pageCount = $pdf->setSourceFile($pdfReader);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }

        fclose($stream);

        return $pageCount;
    }
}
