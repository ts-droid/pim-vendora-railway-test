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

        $customers = Customer::select('id', 'customer_number', 'name', 'country', 'credit_limit', 'credit_terms', 'credit_balance', 'average_payment_days', 'worst_payment_days')
            ->get();

        if ($customers) {
            foreach ($customers as &$customer) {
                $dueInvoices = CustomerInvoice::where('status', 'Open')
                    ->where('customer_number', $customer->customer_number)
                    ->whereNull('paid_at')
                    ->where('due_date', '<', date('Y-m-d'))
                    ->get();

                $customer->due_invoices = $dueInvoices;

                $customer->credit_due = $dueInvoices->sum('amount');

                $customer->average_payment = json_decode($customer->average_payment_days, true);
                $customer->worst_payment = json_decode($customer->worst_payment_days, true);
            }
        }

        return ApiResponseController::success([
            'customers' => $customers->toArray()
        ]);
    }
}
