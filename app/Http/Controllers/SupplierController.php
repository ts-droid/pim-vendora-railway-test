<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(Supplier::class, $request);

        $query = $this->getQueryWithFilter(Supplier::class, $filter);

        $suppliers = $query->get();

        return ApiResponseController::success($suppliers->toArray());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_id' => 'required|string',
            'number' => 'required|string',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $supplier = Supplier::create([
            'external_id' => $request->external_id,
            'number' => $request->number,
            'vat_number' => ($request->vat_number ?? ''),
            'org_number' => ($request->org_number ?? ''),
            'name' => $request->name,
            'brand_name' => ($request->brand_name ?? ''),
            'class_description' => ($request->class_description ?? ''),
            'credit_terms_description' => ($request->credit_terms_description ?? ''),
            'currency' => ($request->currency ?? ''),
            'language' => ($request->language ?? ''),
            'is_supplier' => (int) ($request->is_supplier ?? 0),
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

    public function updateMany(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $supplierIDs = $request->id;

        for ($i = 0;$i < count($supplierIDs);$i++) {
            $supplier = Supplier::find($supplierIDs[$i]);

            if (!$supplier) {
                continue;
            }

            $updateData = [];

            $fillables = (new Supplier)->getFillable();
            foreach ($request->all() as $key => $value) {
                if (in_array($key, $fillables)) {
                    $value = $value[$i] ?? '';
                    $updateData[$key] = is_null($value) ? '' : $value;
                }
            }

            $this->update(new Request($updateData), $supplier);
        }

        return ApiResponseController::success();
    }

    public function markSuppliers()
    {
        $suppliers = Supplier::all();

        if (!$suppliers) {
            return;
        }

        foreach ($suppliers as $supplier) {

            $hasArticles = Article::where('supplier_number', '=', $supplier->number)
                ->where('is_webshop', '=', 1)
                ->exists();

            $isSupplier = $hasArticles ? 1 : 0;

            $this->update(new Request(['is_supplier' => $isSupplier]), $supplier);
        }
    }
}
