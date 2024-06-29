<?php

namespace App\Services\Esign;

use App\Http\Controllers\DoSpacesController;
use App\Models\SignDocument;
use App\Services\PdfMerger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class EsignService
{
    public function generateFile(SignDocument $document)
    {
        if ($document->filename) {
            return $document;
        }

        $filename = $this->generateDocument($document);

        $document->update(['filename' => $filename]);

        return $document;
    }

    public function sendDocument(SignDocument $document)
    {
        // Make sure the document is not already sent
        if ($document->sent_at) {
            return false;
        }

        // Make sure the document has at least 1 recipient
        if ($document->recipients->count() == 0) {
            return false;
        }

        // Send a sign link to the main recipient
        $mainRecipient = $document->mainRecipient();
        if (!$mainRecipient) {
            return false;
        }

        try {
            Mail::to($mainRecipient->email)->queue(new \App\Mail\DocumentSign($document, $mainRecipient));

            $mainRecipient->update(['sent_at' => now()]);
        }
        catch (\Exception $e) {
            log_data('Failed to send signing email to recipient (Document ID: ' . $document->id . ', Recipient ID: ' . $mainRecipient->id . '). (Error: ' . $e->getMessage() . ')');
        }

        $document->update([
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        return true;
    }

    public function appendCertificate(SignDocument $document): void
    {
        $documentContent = $document->getDocumentContent();

        $certificatePdf = Pdf::loadView('esign.sign_certificate', compact('document'));
        $certificateContent = $certificatePdf->output();

        // Merge the certificate with the document
        $pdfMerger = new PdfMerger();
        $mergedPdfContent = $pdfMerger->mergePdfContents($documentContent, $certificateContent);

        DoSpacesController::update($document->filename, $mergedPdfContent);
    }

    private function generateDocument(SignDocument $document): string
    {
        // Generate the base document
        $pdf = Pdf::loadView('esign.document', compact('document'));
        $pdf->setPaper('A4', 'portrait');
        $pdfContent = $pdf->output();

        // Apply template to the document
        $templatePath = public_path('/assets/other/letter_template.pdf');

        $fpdi = new Fpdi();

        $fpdi->setSourceFile($templatePath);
        $templatePageID = $fpdi->importPage(1);

        $pageCount = $fpdi->setSourceFile(StreamReader::createByString($pdfContent));

        for ($i = 1;$i <= $pageCount;$i++) {
            $fpdi->AddPage();

            $fpdi->useTemplate($templatePageID);

            $generatedPageID = $fpdi->importPage($i);
            $fpdi->useTemplate($generatedPageID);
        }

        // Save the final document
        $filename = 'esign/' . time() . '_' . $document->id . '.pdf';

        DoSpacesController::store($filename, $fpdi->Output('S'));

        return $filename;
    }
}
