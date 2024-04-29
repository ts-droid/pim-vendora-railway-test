<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateCustomerRevenue extends Command
{
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
            Customer::where('customer_number', $customerNumber)->update(['revenue' => $revenue]);
        }
    }
}
