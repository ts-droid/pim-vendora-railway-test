<?php

namespace App\Http\Controllers;

use App\Models\SignDocument;
use App\Models\SignDocumentRecipient;
use App\Models\SignTab;
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

    public function deleteTemplate(Request $request, SignTemplate $template)
    {
        // Delete all sections
        if ($template->sections) {
            foreach ($template->sections as $section) {
                $section->delete();
            }
        }

        // Delete the template
        $template->delete();

        return ApiResponseController::success();
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






    public function getDocuments(Request $request)
    {
        $documentsQuery = SignDocument::orderBy('id', 'DESC')
            ->with('recipients');

        if ($request->has('tab_id')) {
            $documentsQuery->where('tab_id', $request->input('tab_id'));
        }

        $documents = $documentsQuery->get();

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
        $data = $request->only([
            'tab_id',
            'template_id',
            'template_sections',
            'system',
            'prompt',
            'document',
            'name',
            'valid_until',
        ]);

        $data['type'] = 'document';

        $document = SignDocument::create($data);

        return ApiResponseController::success($document->toArray());
    }

    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        // Create empty document, to get ID
        $document = SignDocument::create([
            'type' => 'file'
        ]);

        // Upload the document to storage
        $fileContents = $request->file('file')->getContent();

        $filename = 'esign/' . time() . '_' . $document->id . '.pdf';

        $filename = DoSpacesController::store($filename, $fileContents);

        // Update the document with the filename
        $document->update(['filename' => $filename]);

        return ApiResponseController::success($document->toArray());
    }

    public function getDocument(SignDocument $document)
    {
        $document->load('recipients');

        return ApiResponseController::success($document->toArray());
    }

    public function deleteDocument(Request $request, SignDocument $document)
    {
        SignDocumentRecipient::where('sign_document_id', $document->id)->delete();

        if ($document->filename) {
            DoSpacesController::delete($document->filename);
        }

        $document->delete();

        return ApiResponseController::success();
    }

    public function updateDocument(Request $request, SignDocument $document)
    {
        if ($document->status !== 'draft') {
            // Limit what data can be updated
            $document->update($request->only([
                'tab_id',
            ]));
        }
        else {
            $document->update($request->only([
                'tab_id',
                'template_id',
                'template_sections',
                'system',
                'prompt',
                'document',
                'name',
                'valid_until',
            ]));
        }

        return ApiResponseController::success($document->toArray());
    }

    public function sendDocument(SignDocument $document)
    {
        $signService = new EsignService();

        $document = $signService->generateFile($document);

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


    public function getVariables()
    {
        $json = ConfigController::getConfig('esign_variables') ?: '[]';
        $array = json_decode($json, true);

        return ApiResponseController::success($array);
    }

    public function setVariables(Request $request)
    {
        $variables = $request->input('variables') ?: '[]';

        if ($variables) {

            $dbVariables = ConfigController::getConfig('esign_variables') ?: '[]';
            $dbVariables = json_decode($dbVariables, true);

            $variables = json_decode($variables, true);
            foreach ($variables as $key => $value) {
                $dbVariables[$key] = $value;
            }

            ConfigController::setConfigs(['esign_variables' => json_encode($dbVariables)]);
        }

        return ApiResponseController::success();
    }



    public function getTabs()
    {
        $tabs = SignTab::all();

        return ApiResponseController::success($tabs->toArray());
    }

    public function storeTab(Request $request)
    {
        $tab = SignTab::create($request->only([
            'name'
        ]));

        return ApiResponseController::success($tab->toArray());
    }

    public function updateTab(Request $request, SignTab $tab)
    {
        $tab->update($request->only([
            'name'
        ]));

        return ApiResponseController::success($tab->toArray());
    }

    public function deleteTab(SignTab $tab)
    {
        SignDocument::where('tab_id', $tab->id)->update(['tab_id' => 0]);

        $tab->delete();

        return ApiResponseController::success();
    }
}
