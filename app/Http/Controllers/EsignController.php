<?php

namespace App\Http\Controllers;

use App\Models\SignDocument;
use App\Models\SignDocumentRecipient;
use App\Models\SignTemplate;
use App\Models\SignTemplateSection;
use App\Services\Esign\EsignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EsignController extends Controller
{
    public function getTemplates()
    {
        $templates = SignTemplate::all();

        return ApiResponseController::success($templates->toArray());
    }

    public function storeTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $template = SignTemplate::create([
            'title' => $request->input('title')
        ]);

        return ApiResponseController::success($template->toArray());
    }

    public function getTemplate(SignTemplate $template)
    {
        $template->load('sections');

        return ApiResponseController::success($template->toArray());
    }

    public function updateTemplate(Request $request, SignTemplate $template)
    {
        $updates = [];

        if ($request->has('title')) {
            $updates['title'] = $request->input('title');
        }

        $template->update($updates);

        return ApiResponseController::success($template->toArray());
    }

    public function storeSection(Request $request, SignTemplate $template)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $section = SignTemplateSection::create([
            'sign_template_id' => $template->id,
            'title' => $request->input('title'),
            'content' => $request->input('content'),
        ]);

        return ApiResponseController::success($section->toArray());
    }

    public function updateSection(Request $request, SignTemplate $template, SignTemplateSection $section)
    {
        $updates = [];

        if ($request->has('title')) {
            $updates['title'] = $request->input('title');
        }
        if ($request->has('content')) {
            $updates['content'] = $request->input('content');
        }

        $section->update($updates);

        return ApiResponseController::success($section->toArray());
    }

    public function deleteSection(Request $request, SignTemplate $template, SignTemplateSection $section)
    {
        $section->delete();

        return ApiResponseController::success();
    }






    public function getDocuments()
    {
        $documents = SignDocument::orderBy('id', 'DESC')
            ->with('recipients')
            ->get();

        $documentsArray = [];

        if ($documents) {
            foreach ($documents as &$document) {
                $array = $document->toArray();
                $array['access_hash'] = $document->getAccessHash();

                $documentsArray[] = $array;
            }
        }

        return ApiResponseController::success($documentsArray);
    }

    public function storeDocument(Request $request)
    {
        $document = SignDocument::create($request->only([
            'template_id',
            'template_sections',
            'system',
            'prompt',
            'document',
            'name',
        ]));

        return ApiResponseController::success($document->toArray());
    }

    public function getDocument(SignDocument $document)
    {
        $document->load('recipients');

        return ApiResponseController::success($document->toArray());
    }

    public function deleteDocument(Request $request, SignDocument $document)
    {
        if ($document->status !== 'draft') {
            return ApiResponseController::error('Document can not be deleted.');
        }

        SignDocumentRecipient::where('sign_document_id', $document->id)->delete();

        $document->delete();

        return ApiResponseController::success();
    }

    public function updateDocument(Request $request, SignDocument $document)
    {
        if ($document->status !== 'draft') {
            return ApiResponseController::error('Document can not be modified.');
        }

        $document->update($request->only([
            'template_id',
            'template_sections',
            'system',
            'prompt',
            'document',
            'name',
        ]));

        return ApiResponseController::success($document->toArray());
    }

    public function sendDocument(SignDocument $document)
    {
        $signService = new EsignService();
        $success = $signService->sendDocument($document);

        if (!$success) {
            return ApiResponseController::error('Failed to send document.');
        }

        return ApiResponseController::success();
    }

    public function addRecipient(Request $request, SignDocument $document)
    {
        if ($document->status !== 'draft') {
            return ApiResponseController::error('Document can not be modified.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $recipient = SignDocumentRecipient::create([
            'sign_document_id' => $document->id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'access_key' => Str::random(32),
        ]);

        return ApiResponseController::success($recipient->toArray());
    }

    public function deleteRecipient(SignDocument $document, SignDocumentRecipient $recipient)
    {
        if ($document->status !== 'draft') {
            return ApiResponseController::error('Document can not be modified.');
        }

        $recipient->delete();

        return ApiResponseController::success();
    }
}
