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

        $filename = makeFilenameFriendly($document->name . '_signed') . '.pdf';

        return response($document->getDocumentContent())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
