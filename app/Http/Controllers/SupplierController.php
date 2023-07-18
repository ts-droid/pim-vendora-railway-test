<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(Supplier::class, $request);

        if ($filter) {
            $suppliers = Supplier::where($filter)->get();
        }
        else {
            $suppliers = Supplier::all();
        }

        return ApiResponseController::success($suppliers->toArray());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_id' => 'required|string',
            'number' => 'required|string',
            'vat_number' => 'required|string',
            'org_number' => 'required|string',
            'name' => 'required|string',
            'class_description' => 'required|string',
            'credit_terms_description' => 'required|string',
            'currency' => 'required|string',
            'language' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $supplier = Supplier::create([
            'external_id' => $request->external_id,
            'number' => $request->number,
            'vat_number' => $request->vat_number,
            'org_number' => $request->org_number,
            'name' => $request->name,
            'class_description' => $request->class_description,
            'credit_terms_description' => $request->credit_terms_description,
            'currency' => $request->currency,
            'language' => $request->language,
        ]);

        return ApiResponseController::success([$supplier->toArray()]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $fillables = (new Supplier)->getFillable();

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $supplier->{$key} = $value;
            }
        }

        $supplier->save();

        return ApiResponseController::success([$supplier->toArray()]);
    }
}
