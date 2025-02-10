<?php

namespace App\Console\Commands;

use App\Services\WMS\StockOptimizationManager;
use Illuminate\Console\Command;

class ValidateStockMovements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wms:validate-movements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate stock movements. Deletes them if no longer valid.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $manager = new StockOptimizationManager();
        $manager->validate();
    }
}
