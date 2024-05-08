<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Models\Article;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\SalesOrder;
use App\Services\VismaNet\VismaNetApiService;
use Illuminate\Support\Facades\DB;

class CustomerCreditService
{
    public function getAmountDue(string $customerNumber): array
    {
        $dueInvoices = CustomerInvoice::where('status', 'Open')
            ->where('customer_number', $customerNumber)
            ->whereNull('paid_at')
            ->where('due_date', '<', date('Y-m-d'))
            ->get();

        return [
            $dueInvoices->sum('amount'),
            $dueInvoices
        ];
    }

    public function calculateVendoraRating(Customer $customer): void
    {
        // TODO: Implement this method
        return;
    }

    public function calculatePaymentDays(Customer $customer)
    {
        $periods = [3, 6, 12, 24];
        $maxPeriod = max($periods);

        $periodStartDates = [];
        foreach ($periods as $period) {
            $periodStartDates[$period] = date('Y-m-d', strtotime('-' . $period . ' months'));
        }

        // Fetch all invoices for the customer within the periods
        $invoices = CustomerInvoice::where('customer_number', $customer->customer_number)
            ->where('date', '>=', date('Y-m-d', strtotime('-' . $maxPeriod . ' months')))
            ->whereNotNull('paid_at')
            ->get();

        $average = [];
        $worst = [];
        $worstInvoiceIDs = [];

        foreach ($invoices as $invoice) {
            foreach ($periodStartDates as $period => $startDate) {
                if ($invoice->date < $startDate) {
                    continue;
                }

                $days = floor((strtotime($invoice->paid_at) - strtotime($invoice->date)) / 86400);

                // Update average
                if (!isset($average[$period])) {
                    $average[$period] = [0, 0];
                }

                $average[$period][0] += $days;
                $average[$period][1]++;

                // Update worst
                if (!isset($worst[$period])) {
                    $worst[$period] = 0;
                    $worstInvoiceIDs[$period] = 0;
                }

                if ($days > $worst[$period]) {
                    $worst[$period] = $days;
                    $worstInvoiceIDs[$period] = $invoice->id;
                }
            }
        }

        // Calculate averages
        foreach ($average as $key => $value) {
            if ($value[1]) {
                $average[$key] = round($value[0] / $value[1]);
            }
            else {
                $average[$key] = 0;
            }
        }

        // Make sure all values are set
        foreach ($periods as $period) {
            if (!isset($average[$period])) {
                $average[$period] = 0;
            }

            if (!isset($worst[$period])) {
                $worst[$period] = 0;
            }

            if (!isset($worstInvoiceIDs[$period])) {
                $worstInvoiceIDs[$period] = 0;
            }
        }

        // Convert values to json objects to store on customer
        $customer->update([
            'average_payment_days' => json_encode($average),
            'worst_payment_days' => json_encode($worst),
            'worst_payment_invoice_id' => json_encode($worstInvoiceIDs)
        ]);
    }

    /**
     * Calculate and store the current credit balance for a customer
     *
     * @param Customer $customer
     * @return void
     */
    public function calculateCustomerCreditBalance(Customer $customer): void
    {
        $vismaAPI = new VismaNetApiService();

        // Fetch balance from Visma.net
        $balanceResponse = $vismaAPI->callAPI('GET', '/v1/customer/' . $customer->customer_number . '/balance');

        $balance = (float) ($balanceResponse['response']['balance'] ?? 0);


        // Calculate balance from open orders
        $salesOrders = SalesOrder::where('customer', '=', $customer->external_id)->get();

        if ($salesOrders) {
            foreach ($salesOrders as $salesOrder) {
                if (!in_array($salesOrder->status, ['Open', 'Hold', 'BackOrder'])) {
                    continue;
                }

                if ($salesOrder->lines) {
                    foreach ($salesOrder->lines as $salesOrderLine) {
                        $amount = $salesOrderLine->unbilled_amount * $salesOrder->exchange_rate;

                        if ($customer->country == 'SE') {
                            $amount *= 1.25;
                        }

                        $balance += $amount;
                    }
                }
            }
        }

        $customer->update([
            'credit_balance' => $balance
        ]);
    }
}
