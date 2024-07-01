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

        $collectableTitle = ConfigController::getConfig('esign_collectable_title');

        $collectables = $document->getActiveCollectables();

        $collectableLabels = ConfigController::getConfig('esign_collectables_labels') ?: '[]';
        $collectableLabels = json_decode($collectableLabels, true);

        return view('esign.recipient.document', compact('document', 'recipient', 'collectables', 'collectableLabels', 'collectableTitle'));
    }

    public function signDocument(Request $request, SignDocument $document, string $secret)
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

        // Collect all collectables data
        $collectablesData = [];

        if ($recipient->is_main) {
            $collectables = $document->getActiveCollectables();

            foreach ($collectables as $collectable) {
                $value = $request->input('collectable_' . $collectable);

                if (!$value) {
                    return redirect()->back()->with('error', 'Please fill in all required fields.');
                }

                $collectablesData[$collectable] = $value;
            }

            $document->update(['collectables_data' => json_encode($collectablesData)]);
        }


        $recipient->update([
            'signed_at' => now(),
            'ip' => get_user_ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $signService = new EsignService();

        if ($recipient->is_main) {
            // Regenerate the document with the new collected data
            if (count($collectablesData) > 0) {
                $signService->generateFile($document);
            }

            if ($document->recipients->count() > 1) {
                // Send document to all secondary recipients
                $signService->sendDocument($document);
            }
            else {
                // All recipients have signed
                $this->finalizeDocument($document);
            }
        }
        else {
            // Check if all recipients have signed
            $missingSignatures = SignDocumentRecipient::where('sign_document_id', $document->id)
                ->whereNull('signed_at')
                ->count();

            if (!$missingSignatures) {
                // All recipients have signed
                $this->finalizeDocument($document);
            }
        }

        return redirect()->route('esign.document', ['document' => $document, 'secret' => $secret]);
    }

    private function finalizeDocument(SignDocument $document)
    {
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
