<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateCustomerRevenue extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate-customer-revenue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates the revenue year to date for all customers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting customer revenue calculation.', $this->commandLogContext());

        $startDate = date('Y-01-01');
        $endDate = date('Y-m-d');

        $invoices = DB::table('customer_invoices')
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $customerRevenues = [];

        foreach ($invoices as $invoice) {
            if (!isset($customerRevenues[$invoice->customer_number])) {
                $customerRevenues[$invoice->customer_number] = 0;
            }

            $customerRevenues[$invoice->customer_number] += $invoice->amount;
        }

        // Reset revenue for all customers
        DB::table('customers')->update(['revenue' => 0]);

        // Set new revenue for customers
        foreach ($customerRevenues as $customerNumber => $revenue) {
            $customer = Customer::where('customer_number', $customerNumber)->first();

            if (!$customer) {
                continue;
            }

            $customer->revenue = (int) $revenue;
            $customer->save();
        }

        action_log('Finished customer revenue calculation.', $this->commandLogContext([
            'period_start' => $startDate,
            'period_end' => $endDate,
            'invoice_count' => $invoices->count(),
            'customers_with_revenue' => count($customerRevenues),
        ]));
    }
}
