<?php

namespace App\Http\Controllers;

use App\Mail\DocumentSigned;
use App\Models\SignDocument;
use App\Models\SignDocumentRecipient;
use App\Services\Esign\EsignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EsignRecipientController extends Controller
{
    public function document(SignDocument $document, string $secret)
    {
        $recipient = SignDocumentRecipient::where('sign_document_id', $document->id)
            ->where('access_key', $secret)
            ->first();

        if (!$recipient) {
            abort(404);
        }

        $collectables = $document->collectables ? json_decode($document->collectables, true) : [];
        $collectables = array_filter($collectables, function ($collectable) use ($document) {
            return in_array($collectable, $document->document);
        });

        return view('esign.recipient.document', compact('document', 'recipient', 'collectables'));
    }

    public function signDocument(SignDocument $document, string $secret)
    {
        $recipient = SignDocumentRecipient::where('sign_document_id', $document->id)
            ->where('access_key', $secret)
            ->first();

        if (!$recipient) {
            abort(404);
        }

        if ($recipient->signed_at) {
            abort(401);
        }

        $recipient->update([
            'signed_at' => now(),
            'ip' => get_user_ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Check if all recipients have signed
        $missingSignatures = SignDocumentRecipient::where('sign_document_id', $document->id)
            ->whereNull('signed_at')
            ->count();

        if (!$missingSignatures) {
            // All recipients have signed
            $document->update([
                'signed_at' => now(),
                'status' => 'signed',
            ]);

            // Append signature to PDF
            $signService = new EsignService();
            $signService->appendCertificate($document);

            // Generate and store hash of signed PDF
            $documentContent = $document->getDocumentContent();

            $document->update([
                'hash' => hash('sha256', $documentContent)
            ]);

            // Email signed document to all recipients
            foreach ($document->recipients as $recipient) {
                try {
                    Mail::to($recipient->email)->queue(new DocumentSigned($document, $recipient));
                }
                catch (\Exception $e) {
                    // Silent fail
                }
            }
        }

        return redirect()->route('esign.document', ['document' => $document, 'secret' => $secret]);
    }

    public function downloadDocument(SignDocument $document, string $secret)
    {
        $recipient = SignDocumentRecipient::where('sign_document_id', $document->id)
            ->where('access_key', $secret)
            ->first();

        if (!$recipient) {
            abort(404);
        }

        if (!$document->filename) {
            abort(404);
        }

        return response()->download($document->filename, $document->name . '.pdf');
    }
}
