<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use Illuminate\Http\Request;

class PaymentReportController extends Controller
{
    public function index(Request $request)
    {
        $period = (int) $request->input('period', 6);
        $startDate = $request->input('start_date') ?: date('Y-m-d');
        $endDate = $request->input('end_date') ?: date('Y-m-d');

        $customers = Customer::select('id', 'customer_number', 'name', 'country', 'credit_limit', 'credit_balance')
            ->get();

        if ($customers) {
            foreach ($customers as &$customer) {
                $customer->credit_due = CustomerInvoice::where('status', 'Open')
                    ->where('customer_number', $customer->customer_number)
                    ->whereNull('paid_at')
                    ->where('due_date', '<', date('Y-m-d'))
                    ->sum('amount');

                $averagePaymentDats = json_decode($customer->average_payment_days, true);
                $customer->average_payment_days = $averagePaymentDats[$period] ?? 0;

                $worstPaymentDats = json_decode($customer->worst_payment_days, true);
                $customer->worst_payment_days = $worstPaymentDats[$period] ?? 0;
            }
        }

        return ApiResponseController::success([
            'customers' => $customers->toArray()
        ]);
    }
}
