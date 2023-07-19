<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(Customer::class, $request);

        if ($filter) {
            $customers = Customer::where($filter)->get();
        }
        else {
            $customers = Customer::all();
        }

        return ApiResponseController::success($customers->toArray());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_id' => 'required|string',
            'customer_number' => 'required|string',
            'vat_number' => 'required|string',
            'org_number' => 'required|string',
            'name' => 'required|string'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $customer = Customer::create([
            'external_id' => $request->external_id,
            'customer_number' => $request->customer_number,
            'vat_number' => $request->vat_number,
            'org_number' => $request->org_number,
            'name' => $request->name
        ]);

        return ApiResponseController::success([$customer->toArray()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $fillables = (new Customer)->getFillable();

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $customer->{$key} = $value;
            }
        }

        $customer->save();

        return ApiResponseController::success([$customer->toArray()]);
    }

    public function VATNumberToCustomerNumber(array $VATNumbers)
    {
        $VATNumbers = array_filter($VATNumbers);

        if (!$VATNumbers) {
            return [];
        }

        return Customer::whereIn('vat_number', $VATNumbers)
            ->where('customer_number', '!=', '')
            ->whereNotNull('customer_number')
            ->pluck('customer_number')
            ->toArray();
    }
}
