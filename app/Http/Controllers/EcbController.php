<?php

namespace App\Http\Controllers;

use App\Services\EcbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EcbController extends Controller
{
    public function convertCurrency(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $validator = Validator::make($request->all(), [
            'number' => 'required',
            'fromCurrency' => 'required',
            'toCurrency' => 'required',
            'date' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        try {
            $service = new EcbService();
            $convertedNumber = $service->convertCurrency(
                (float) $request->input('number'),
                (string) $request->input('fromCurrency'),
                (string) $request->input('toCurrency'),
                (string) $request->input('date')
            );
        } catch (\Exception $e) {
            return ApiResponseController::error($e->getMessage());
        }

        return ApiResponseController::success([
            'number' => $convertedNumber
        ]);
    }
}
