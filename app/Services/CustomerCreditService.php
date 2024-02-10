<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Models\Article;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Services\VismaNet\VismaNetApiService;
use Illuminate\Support\Facades\DB;

class CustomerCreditService
{
    public function getAmountDue(string $customerNumber): float
    {
        // TODO: Implement this method
        return 0;
    }

    public function calculateVendoraRating(Customer $customer): void
    {
        // TODO: Implement this method
        return;
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
