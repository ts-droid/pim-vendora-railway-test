<?php

namespace App\Console\Commands;

use App\Services\WMS\StockOptimizationManager;
use Illuminate\Console\Command;

class OptimizeStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wms:optimize-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize stock placements';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $manager = new StockOptimizationManager();
        $manager->optimize();
    }
}
