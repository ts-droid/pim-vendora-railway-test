<?php

namespace App\Http\Controllers;

use App\Services\EcbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EcbController extends Controller
{
    public function convertCurrency(Request $request)
    {
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

        return $convertedNumber;
    }
}
