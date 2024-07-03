<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Services\Allianz\AllianzGradeCover;
use App\Services\CustomerCreditService;
use Illuminate\Http\Request;

class PaymentReportController extends Controller
{
    public function index(Request $request)
    {
        $period = (int) $request->input('period', 6);
        $startDate = $request->input('start_date') ?: date('Y-m-d');
        $endDate = $request->input('end_date') ?: date('Y-m-d');

        $customers = Customer::select('id', 'customer_number', 'name', 'country', 'credit_limit', 'credit_terms', 'credit_balance', 'average_payment_days', 'worst_payment_days', 'worst_payment_invoice_id')
            ->get();

        $customerCreditService = new CustomerCreditService();
        $allianzGradeCover = new AllianzGradeCover();

        if ($customers) {
            foreach ($customers as &$customer) {
                $customer->grade = $allianzGradeCover->getCustomerGrade($customer);

                list($customer->credit_due, $customer->due_invoices) = $customerCreditService->getAmountDue($customer->customer_number);

                $customer->average_payment = json_decode($customer->average_payment_days, true);
                $customer->worst_payment = json_decode($customer->worst_payment_days, true);

                $customer->worst_payment_invoice_id = json_decode($customer->worst_payment_invoice_id, true);

                $worstPaymentInvoices = [];

                if ($customer->worst_payment_invoice_id) {
                    foreach ($customer->worst_payment_invoice_id as $period => $invoiceID) {
                        $worstPaymentInvoices[$period] = CustomerInvoice::where('id', $invoiceID)->first();
                    }
                }

                $customer->worst_payment_invoice = $worstPaymentInvoices;
            }
        }

        return ApiResponseController::success([
            'customers' => $customers->toArray()
        ]);
    }
}
