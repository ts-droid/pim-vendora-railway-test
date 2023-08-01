<?php

namespace App\Console;

use App\Http\Controllers\SupplierController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('visma:fetch')->dailyAt('02:00');

        $schedule->command('wgr:fetch')->dailyAt('05:00');

        $schedule->call(function() {
            $supplierController = new SupplierController();
            $supplierController->markSuppliers();
        })->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
