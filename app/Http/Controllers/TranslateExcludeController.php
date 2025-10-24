<?php

namespace App\Http\Controllers;

use App\Services\TranslateExcludeService;
use Illuminate\Http\Request;

class TranslateExcludeController extends Controller
{
    public function get()
    {
        return ApiResponseController::success(
            TranslateExcludeService::getAll()
        );
    }

    public function store(Request $request)
    {
        TranslateExcludeService::add(
            (string) $request->input('value', '')
        );

        return ApiResponseController::success();
    }

    public function delete(Request $request)
    {
        TranslateExcludeService::remove(
            (string) $request->input('value', '')
        );

        return ApiResponseController::success();
    }
}
