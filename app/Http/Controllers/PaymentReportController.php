<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class PaymentReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date') ?: date('Y-m-d');
        $endDate = $request->input('end_date') ?: date('Y-m-d');

        $customers = Customer::select('id', 'name', 'country', 'credit_limit', 'credit_balance')
            ->get();

        return ApiResponseController::success([
            'customers' => $customers->toArray()
        ]);
    }
}
