<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromptAPIController extends Controller
{
    public function execute(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt_id' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $inputs = $request->input('inputs');
        $inputs = $inputs ? json_decode($inputs, true) : [];

        $promptController = new PromptController();
        $response = $promptController->execute(
            $request->input('prompt_id'),
            $inputs
        );

        return ApiResponseController::success(['response' => $response]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt_id' => 'required',
            'inputs' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $inputs = $request->input('inputs');
        $inputs = $inputs ? json_decode($inputs, true) : [];

        $promptController = new PromptController();
        $prompt = $promptController->store(
            $request->input('prompt_id'),
            $request->input('system_code'),
            $request->input('group'),
            $request->input('name'),
            $request->input('system'),
            $request->input('message'),
            $inputs
        );

        return ApiResponseController::success($prompt->toArray());
    }

    public function getAll(Request $request)
    {
        $prompts = Prompt::orderBy('name', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        return ApiResponseController::success($prompts->toArray());
    }

    public function get(Request $request, Prompt $prompt)
    {
        $promptController = new PromptController();
        $prompt = $promptController->get($prompt->id);

        return ApiResponseController::success($prompt->toArray());
    }

    public function getBySystemCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'system_code' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode(
            $request->input('system_code')
        );

        return ApiResponseController::success($prompt->toArray());
    }

    public function getGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $promptController = new PromptController();
        $prompts = $promptController->getGroup(
            $request->input('group')
        );

        return ApiResponseController::success($prompts->toArray());
    }
}
