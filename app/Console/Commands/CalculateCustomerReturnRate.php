<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateCustomerReturnRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate-customer-return-rate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates the return rate for all customers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = '2023-01-01';
        $endDate = date('Y-m-d');

        $customers = Customer::all();

        foreach ($customers as $customer) {
            $revenue = DB::table('sales_orders')
                ->where('customer', $customer->external_id)
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate)
                ->where('order_type', '!=', 'RC')
                ->sum('order_total');

            if (!$revenue) {
                $customer->update(['return_rate' => 0]);
                continue;
            }

            $returnValue = DB::table('sales_orders')
                ->where('customer', $customer->external_id)
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate)
                ->where('order_type', 'RC')
                ->sum('order_total');

            $customer->update([
                'return_rate' => round($returnValue / $revenue * 100, 2)
            ]);
        }
    }
}
