<?php

namespace App\Http\Controllers;

use App\Models\SignTemplate;
use App\Models\SignTemplateSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
}
