<?php

namespace App\Console;

use App\Http\Controllers\SupplierController;
use App\Services\LanguageFieldTranslator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\App;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run only in production
        if (App::environment('production')) {

            $schedule->command('meta-data:generate-articles')->everyFiveMinutes()->withoutOverlapping();

            $schedule->command('translate-database')->everyFiveMinutes()->withoutOverlapping();

            $schedule->command('visma:fetch')->dailyAt('02:00');

            $schedule->command('wgr:fetch')->dailyAt('05:00');

            $schedule->command('articles:calculate-sales-volume')->dailyAt('06:00');
            $schedule->command('customers:calculate-sales')->dailyAt('08:00');

            $schedule->command('mark-suppliers')->daily();
        }

        // Run only in staging
        if (App::environment('staging')) {
            $schedule->command('database:sync')->days([1, 4]); // 1 for Monday and 4 for Thursday
        }

        // Run in all environments
        $schedule->command('purchase-order:generate-weights')->dailyAt('03:00');
        $schedule->command('purchase-orders:generate')->dailyAt('13:00');
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
