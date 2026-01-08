<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Services\VismaNet\VismaNetSupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    public function get(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $filter = $this->getModelFilter(Supplier::class, $request);

        $query = $this->getQueryWithFilter(Supplier::class, $filter);

        $suppliers = $query->get();

        return ApiResponseController::success($suppliers->toArray());
    }

    public function getBasic(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $filters = $request->input('filter');
        $columns = $request->input('columns', ['*']);

        if (!in_array('*', $columns) && !in_array('created_at', $columns)) {
            $columns[] = 'created_at';
        }

        $query = DB::table('suppliers')
            ->select($columns);

        if ($filters) {
            foreach ($filters as $filter) {
                $count = count($filter);

                if (is_array($filter[0])) {
                    $query->where(function($query) use ($filter) {
                        foreach ($filter as $subFilter) {
                            $subCount = count($subFilter);

                            if ($subCount === 3) {
                                $query->orWhere($subFilter[0], $subFilter[1], $subFilter[2]);
                            }
                            else if ($subCount === 2) {
                                $query->orWhereIn($subFilter[0], $subFilter[1]);
                            }
                        }
                    });
                }
                else if ($count === 3) {
                    $query->where($filter[0], $filter[1], $filter[2]);
                }
                elseif ($count === 2) {
                    $query->whereIn($filter[0], $filter[1]);
                }
            }
        }

        // Execute query
        $suppliers = $query->orderBy('created_at', 'DESC')->get()->toArray();

        // Convert supplier objects into an array
        $suppliers = array_map(function ($supplier) {
            return get_object_vars($supplier);
        }, $suppliers);

        return ApiResponseController::success($suppliers);
    }

    public function store(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $validator = Validator::make($request->all(), [
            'number' => 'required|string',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $data = $request->all();
        $data['access_key'] = Str::random(32);

        DB::beginTransaction();

        $supplier = Supplier::create($data);

        // Create the supplier in Visma.net
        if (empty($data['external_id'])) {
            $vismaNetSupplierService = new VismaNetSupplierService();
            $response = $vismaNetSupplierService->createSupplier($supplier);

            if (!$response['success']) {
                DB::rollBack();
                return ApiResponseController::error($response['error_message']);
            }
        }

        DB::commit();

        return ApiResponseController::success([$supplier->toArray()]);
    }

    public function getSupplier(Supplier $supplier)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $supplier->load('contacts');

        return ApiResponseController::success($supplier->toArray());
    }

    public function update(Request $request, Supplier $supplier)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $fillables = (new Supplier)->getFillable();

        $updateData = [];

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $updateData[$key] = is_null($value) ? '' : $value;
            }
        }

        $supplier->update($updateData);

        return ApiResponseController::success([$supplier->toArray()]);
    }

    public function updateMany(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        return;

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

    public function storeContact(Request $request, Supplier $supplier)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $contact = SupplierContact::create([
            'supplier_id' => $supplier->id,
            'name' => (string) $request->input('name'),
            'attention' => (string) $request->input('attention'),
            'email' => (string) $request->input('email'),
            'phone1' => (string) $request->input('phone1'),
            'phone2' => (string) $request->input('phone2'),
            'address_line' => (string) $request->input('address_line'),
            'address_city' => (string) $request->input('address_city'),
            'address_country' => (string) $request->input('address_country')
        ]);

        return APiResponseController::success($contact->toArray());
    }

    public function updateContact(Request $request, Supplier $supplier, SupplierContact $supplierContact)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $data = $request->all();

        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $data[$key] = '';
            }
        }

        $supplierContact->update($data);

        return ApiResponseController::success($supplierContact->toArray());
    }

    public function deleteContact(Request $request, Supplier $supplier, SupplierContact $supplierContact)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $supplierContact->delete();

        return ApiResponseController::success();
    }
}
