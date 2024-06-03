<?php

namespace App\Http\Controllers;

use App\Models\SignDocument;
use App\Services\Esign\EsignService;
use Illuminate\Http\Request;

class EsignRecipientController extends Controller
{
    public function document(SignDocument $document, string $secret)
    {
        if ($document->getAccessHash() !== $secret) {
            abort(404);
        }

        return view('esign.recipient.document', compact('document'));
    }

    public function signDocument(SignDocument $document, string $secret)
    {
        if ($document->getAccessHash() !== $secret) {
            abort(404);
        }

        if ($document->signed_at) {
            abort(401);
        }

        $document->update([
            'signed_at' => now(),
            'sign_ip' => request()->ip(),
            'sign_user_agent' => request()->userAgent(),
            'status' => 'signed'
        ]);

        // Append signature to PDF
        $signService = new EsignService();
        $signService->appendCertificate($document);

        // Generate and store hash of signed PDF
        $document->update([
            'hash' => hash_file('sha256', $document->filename)
        ]);

        return redirect()->route('esign.document', ['document' => $document, 'secret' => $secret]);
    }

    public function downloadDocument(SignDocument $document, string $secret)
    {
        if ($document->getAccessHash() !== $secret) {
            abort(404);
        }

        if (!$document->filename) {
            abort(404);
        }

        return response()->download($document->filename, $document->name . '.pdf');
    }
}
