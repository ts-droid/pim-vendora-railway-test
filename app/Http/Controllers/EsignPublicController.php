<?php

namespace App\Http\Controllers;

use App\Models\SignDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class EsignPublicController extends Controller
{
    public function preview(SignDocument $document, string $accessHash)
    {
        if ($accessHash !== $document->getAccessHash()) {
            abort(404);
        }

        $fileContent = $document->getDocumentContent();

        return response()->streamDownload(function() use ($fileContent) {
            echo $fileContent;
        }, 'document.pdf', ['Content-Type' => 'application/pdf']);
    }
}
