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
    protected $signature = 'purchase-orders:generate {supplierID?} {isEmpty?} {runSync?}';

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
        $isEmpty = $this->argument('isEmpty') ?? 0;
        $runSync = $this->argument('runSync') ?? 0;

        if ($runSync) {
            $job = new \App\Jobs\GeneratePurchaseOrders($supplierID, $isEmpty);
            $job->handle();
            $this->info('Purchase order generated');
        } else {
            \App\Jobs\GeneratePurchaseOrders::dispatch($supplierID, $isEmpty)->onQueue('high');
            $this->info('Generating purchase orders...');
        }
    }
}
