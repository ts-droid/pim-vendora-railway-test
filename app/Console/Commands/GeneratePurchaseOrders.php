<?php

namespace App\Console\Commands;

use App\Services\PurchaseOrderGenerator;
use Illuminate\Console\Command;

class GeneratePurchaseOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase-orders:generate {supplierID?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate purchase orders for all suppliers.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $supplierID = $this->argument('supplierID') ?? 0;

        \App\Jobs\GeneratePurchaseOrders::dispatch($supplierID);

        $this->info('Generating purchase orders...');
    }
}
