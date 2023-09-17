<?php

namespace App\Console\Commands;

use App\Http\Controllers\CustomerController;
use Illuminate\Console\Command;

class CalculateCustomerSales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:calculate-sales';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate historic sales for each customers.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $customerController = new CustomerController();
        $customerController->calculateSales();
    }
}
