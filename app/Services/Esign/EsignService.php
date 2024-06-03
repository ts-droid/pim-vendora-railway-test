<?php

namespace App\Services\Esign;

use App\Models\SignDocument;
use App\Services\PdfMerger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

class EsignService
{
    public function sendDocument(SignDocument $document)
    {
        // Make sure the document is not already sent
        if ($document->sent_at) {
            return false;
        }

        // Make sure a recipient email and name is set
        if (!$document->recipient_email || !$document->recipient_name) {
            return false;
        }

        // Generate the document
        $filePath = $this->generateDocument($document);

        $document->update(['filename' => $filePath]);

        // Send the sign link to the recipient
        try {
            Mail::to($document->recipient_email)->queue(new \App\Mail\DocumentSign($document));
            $document->update([
                'sent_at' => now(),
                'status' => 'sent',
            ]);
        }
        catch (\Exception $e) {
            dd($e->getMessage());
            log_data('Failed to send signing email to recipient (Document ID: ' . $document->id . '). (Error: ' . $e->getMessage() . ')');
        }

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

        $pdf = Pdf::loadView('esign.document', compact('document'));
        $pdf->save($filePath);

        return $filePath;
    }
}
