<?php

namespace App\Console\Commands;

use App\Http\Controllers\ConfigController;
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
        $isProduction = (int) ConfigController::getConfig('wms_production_mode');
        $lastRun = (int) ConfigController::getConfig('last_wms_optimize_stock');
        $schedule = ConfigController::getConfig('wms_movement_job_schedule');

        if (!$isProduction) {
            return;
        }

        switch ($schedule) {
            case 'daily':
                $nextRun = $lastRun + 86_400;
                break;

            case 'everyOtherDay':
                $nextRun = $lastRun + (2 * 86_400);
                break;

            case 'everyThreeDays':
                $nextRun = $lastRun + (3 * 86_400);
                break;

            case 'weekly':
                $nextRun = $lastRun + (7 * 86_400);
                break;

            case 'never':
            default:
                return;
        }

        if (time() < $nextRun) {
            return;
        }

        $manager = new StockOptimizationManager();
        $success = $manager->optimize();

        if ($success) {
            ConfigController::setConfigs(['last_wms_optimize_stock' => time()]);
        }
    }
}
