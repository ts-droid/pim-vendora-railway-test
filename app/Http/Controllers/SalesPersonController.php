<?php

namespace App\Http\Controllers;

use App\Models\SalesPerson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalesPersonController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(SalesPerson::class, $request);

        $query = $this->getQueryWithFilter(SalesPerson::class, $filter);

        $salesPersons = $query->get();

        return ApiResponseController::success($salesPersons->toArray());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_id' => 'required|string',
            'name' => 'required|string'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $salesPerson = SalesPerson::create([
            'external_id' => $request->external_id,
            'name' => $request->name
        ]);

        return ApiResponseController::success([$salesPerson->toArray()]);
    }

    public function update(Request $request, SalesPerson $salesPerson)
    {
        $fillables = (new SalesPerson)->getFillable();

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $salesPerson->{$key} = $value;
            }
        }

        $salesPerson->save();

        return ApiResponseController::success([$salesPerson->toArray()]);
    }
}
