<?php

namespace App\Services\Esign;

use App\Models\SignDocument;
use App\Services\PdfMerger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use setasign\Fpdi\Fpdi;

class EsignService
{
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

        // Generate the document
        $filePath = $this->generateDocument($document);

        $document->update(['filename' => $filePath]);

        // Send a sign link to each recipient
        foreach ($document->recipients as $recipient) {
            try {
                Mail::to($recipient->email)->queue(new \App\Mail\DocumentSign($document, $recipient));

                $recipient->update(['sent_at' => now()]);
            }
            catch (\Exception $e) {
                log_data('Failed to send signing email to recipient (Document ID: ' . $document->id . ', Recipient ID: ' . $recipient->id . '). (Error: ' . $e->getMessage() . ')');
            }
        }

        $document->update([
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        return true;
    }

    public function appendCertificate(SignDocument $document): void
    {
        $directory = storage_path('app/esign');
        $filePath = $directory . '/certificate_' . $document->id . '.pdf';

        // Make sure directory exists
        if (!is_dir($directory)) {
            mkdir($directory);
        }

        // Make sure the file does not already exist
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $pdf = Pdf::loadView('esign.sign_certificate', compact('document'));
        $pdf->save($filePath);

        // Merge the certificate with the document
        $pdfMerger = new PdfMerger();
        $pdfMerger->mergePdfs($document->filename, $filePath, $document->filename);

        // Remove the certificate file
        @unlink($filePath);
    }

    private function generateDocument(SignDocument $document): string
    {
        $directory = storage_path('app/esign');
        $filePath = $directory . '/' . $document->id . '.pdf';

        // Make sure directory exists
        if (!is_dir($directory)) {
            mkdir($directory);
        }

        // Make sure the file does not already exist
        if (file_exists($filePath)) {
            return $filePath;
        }

        // Generate the document
        $pdf = Pdf::loadView('esign.document', compact('document'));
        $pdf->setPaper('A4', 'portrait');
        $pdf->save($filePath);

        // Apply template to the document
        $templatePath = public_path('/assets/other/letter_template.pdf');

        $fpdi = new Fpdi();

        $fpdi->setSourceFile($templatePath);
        $templatePageID = $fpdi->importPage(1);

        $pageCount = $fpdi->setSourceFile($filePath);

        for ($i = 1;$i <= $pageCount;$i++) {
            $fpdi->AddPage();

            $fpdi->useTemplate($templatePageID);

            $generatedPageID = $fpdi->importPage($i);
            $fpdi->useTemplate($generatedPageID);
        }

        // Save the final document
        @unlink($filePath);
        file_put_contents($filePath, $fpdi->Output('S'));

        return $filePath;
    }
}
