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

            $schedule->command('visma:check')->everyTwoMinutes()->withoutOverlapping();

            $schedule->command('meta-data:generate-articles')->everyFiveMinutes()->withoutOverlapping();

            $schedule->command('translate-database')->everyFiveMinutes()->withoutOverlapping();

            $schedule->command('servers:monitor')->everyTenMinutes()->withoutOverlapping();

            $schedule->command('visma:process-queue')->everyMinute()->withoutOverlapping();

            $schedule->command('purchase-orders:send-reminders')->hourly();

            // Visma.net Fetching
            $schedule->command('visma:fetch customers')->dailyAt('02:00');
            $schedule->command('visma:fetch sales-persons')->dailyAt('02:10');
            $schedule->command('visma:fetch suppliers')->dailyAt('02:20');
            $schedule->command('visma:fetch invoices')->dailyAt('02:30');
            $schedule->command('visma:fetch purchase-orders')->dailyAt('02:40');
            $schedule->command('visma:fetch inventory-receipts')->dailyAt('02:50');
            $schedule->command('visma:fetch currency')->dailyAt('03:00');
            $schedule->command('visma:fetch currency')->dailyAt('03:10');
            $schedule->command('visma:fetch sales-orders')->dailyAt('03:20');
            $schedule->command('visma:fetch articles')->hourly();

            $schedule->command('wgr:fetch')->dailyAt('05:00');

            $schedule->command('articles:calculate-sales-volume')->dailyAt('06:00');
            $schedule->command('customers:calculate-sales')->dailyAt('08:00');

            $schedule->command('mark-suppliers')->daily();
            $schedule->command('bestsellers:calculate')->daily();
            $schedule->command('articles:process-eol')->daily();
        }

        // Run only in staging
        if (App::environment('staging')) {
            $schedule->command('database:sync')->days([1, 4]); // 1 for Monday and 4 for Thursday
        }

        // Run in all environments
        $schedule->command('api-logger:clean')->hourly();
        $schedule->command('purchase-order:generate-weights')->dailyAt('03:00');
        //$schedule->command('purchase-orders:generate')->dailyAt('13:00');
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
