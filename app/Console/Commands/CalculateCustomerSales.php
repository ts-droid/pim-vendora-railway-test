<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Http\Controllers\CustomerController;
use Illuminate\Console\Command;

class CalculateCustomerSales extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting customer sales calculation.', $this->commandLogContext());

        $customerController = new CustomerController();
        $customerController->calculateSales();

        action_log('Finished customer sales calculation.', $this->commandLogContext());
    }
}
