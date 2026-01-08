<?php

namespace App\Http\Controllers;

use App\Services\TranslateExcludeService;
use Illuminate\Http\Request;

class TranslateExcludeController extends Controller
{
    public function get()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        return ApiResponseController::success(
            TranslateExcludeService::getAll()
        );
    }

    public function store(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        TranslateExcludeService::add(
            (string) $request->input('value', '')
        );

        return ApiResponseController::success();
    }

    public function delete(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        TranslateExcludeService::remove(
            (string) $request->input('value', '')
        );

        return ApiResponseController::success();
    }
}
