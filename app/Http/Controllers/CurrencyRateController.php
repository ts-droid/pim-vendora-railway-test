<?php

namespace App\Http\Controllers;

use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CurrencyRateController extends Controller
{
    public function get(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $filter = $this->getModelFilter(CurrencyRate::class, $request);

        $query = $this->getQueryWithFilter(CurrencyRate::class, $filter);

        $currencyRates = $query->get();

        return ApiResponseController::success($currencyRates->toArray());
    }

    public function store(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $validator = Validator::make($request->all(), [
            'external_id' => 'required|string',
            'from_currency' => 'required|string',
            'to_currency' => 'required|string',
            'type' => 'required|string',
            'rate' => 'required',
            'date' => 'required|string',
            'mult_div' => 'required|string',
            'rate_reciprocal' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $currencyRate = CurrencyRate::create([
            'external_id' => (string) $request->external_id,
            'from_currency' => (string) $request->from_currency,
            'to_currency' => (string) $request->to_currency,
            'type' => (string) $request->type,
            'rate' => (float) $request->rate,
            'date' => (string) $request->date,
            'mult_div' => (string) $request->mult_div,
            'rate_reciprocal' => (float) $request->rate_reciprocal,
        ]);

        return ApiResponseController::success($currencyRate->toArray());
    }

    public function update(Request $request, CurrencyRate $currencyRate)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $fillables = (new CurrencyRate())->getFillable();

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $currencyRate->{$key} = $value;
            }
        }

        $currencyRate->save();

        return ApiResponseController::success($currencyRate->toArray());
    }
}
