<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use Illuminate\Console\Command;

class GeneratePurchaseOrders extends Command
{
    use ProvidesCommandLogContext;

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

        action_log('Starting purchase order generation.', $this->commandLogContext([
            'supplier_id' => $supplierID,
            'is_empty' => (bool) $isEmpty,
            'run_sync' => (bool) $runSync,
        ]));

        if ($runSync) {
            $job = new \App\Jobs\GeneratePurchaseOrders($supplierID, $isEmpty);
            $job->handle();
            $this->info('Purchase order generated');

            action_log('Completed synchronous purchase order generation.', $this->commandLogContext([
                'supplier_id' => $supplierID,
            ]));
        } else {
            \App\Jobs\GeneratePurchaseOrders::dispatch($supplierID, $isEmpty)->onQueue('high');
            $this->info('Generating purchase orders...');

            action_log('Queued purchase order generation job.', $this->commandLogContext([
                'supplier_id' => $supplierID,
            ]));
        }
    }
}
