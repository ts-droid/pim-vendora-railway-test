<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use Illuminate\Http\Request;

class PaymentReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date') ?: date('Y-m-d');
        $endDate = $request->input('end_date') ?: date('Y-m-d');

        $customers = Customer::select('id', 'name', 'country', 'credit_limit', 'credit_balance')
            ->get();

        if ($customers) {
            foreach ($customers as &$customer) {
                $customer->credit_due = CustomerInvoice::where('status', 'Open')
                    ->where('customer_number', $customer->customer_number)
                    ->whereNull('paid_at')
                    ->sum('total');
            }
        }

        return ApiResponseController::success([
            'customers' => $customers->toArray()
        ]);
    }
}
