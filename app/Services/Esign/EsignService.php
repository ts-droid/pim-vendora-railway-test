<?php

namespace App\Services\Esign;

use App\Enums\LaravelQueues;
use App\Http\Controllers\DoSpacesController;
use App\Models\SignDocument;
use App\Models\SignDocumentRecipient;
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
            // Generate new file and replace the old one
            $this->generateDocument($document, $document->filename);
        }
        else {
            // Generate new file
            $filename = $this->generateDocument($document);

            $document->update(['filename' => $filename]);
        }

        return $document;
    }

    public function sendDocument(SignDocument $document, bool $forceMain = false)
    {
        if ($document->sent_at && !$forceMain) {
            // Send to secondary recipients
            return $this->sendDocumentSecondary($document);
        }
        else {
            // Send to main recipient
            return $this->sendDocumentMain($document);
        }
    }

    private function sendDocumentMain(SignDocument $document)
    {
        // Make sure the document is not already sent
        // Send a sign link to the main recipient
        $mainRecipient = $document->mainRecipient();
        if (!$mainRecipient) {
            return false;
        }

        $sendSuccess = $this->sendDocumentToRecipient($document, $mainRecipient);
        if (!$sendSuccess) {
            return false;
        }

        $document->update([
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        return true;
    }

    private function sendDocumentSecondary(SignDocument $document)
    {
        // Make sure the document has more than one recipient
        if ($document->recipients->count() < 2) {
            return false;
        }

        // Make sure the document is already sent to the main recipient
        if (!$document->sent_at) {
            return false;
        }

        // Make sure the main recipient has signed the document
        $mainRecipient = $document->mainRecipient();
        if (!($mainRecipient->signed_at ?? null)) {
            return false;
        }

        // Send a sign link to all secondary recipients
        foreach ($document->recipients as $recipient) {
            if ($recipient->is_main) {
                continue;
            }

            $this->sendDocumentToRecipient($document, $recipient);
        }

        return true;
    }

    private function sendDocumentToRecipient(SignDocument $document, SignDocumentRecipient $recipient)
    {
        try {
            Mail::to($recipient->email)->queue((new \App\Mail\DocumentSign($document, $recipient))->onQueue(LaravelQueues::MAIL->value));

            $recipient->update(['sent_at' => now()]);
        }
        catch (\Exception $e) {
            log_data('Failed to send signing email to recipient (Document ID: ' . $document->id . ', Recipient ID: ' . $recipient->id . '). (Error: ' . $e->getMessage() . ')');
            return false;
        }

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

    public function getDocumentFileContent(SignDocument $document)
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

        return $fpdi->Output('S');
    }

    private function generateDocument(SignDocument $document, string $filename = ''): string
    {
        // Remove the old file
        if ($filename) {
            DoSpacesController::delete($filename);
        }

        // Save the final document
        $filename = $filename ?: ('esign/' . time() . '_' . $document->id . '.pdf');

        DoSpacesController::store($filename, $this->getDocumentFileContent($document));

        return $filename;
    }
}
