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
            $schedule->command('purchase-orders:send-reminders')->cron('0 0 */4 * *');

            // TODO: Can me moved to long loved processes
            $schedule->command('meta-data:generate-articles')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('translate-database')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('servers:monitor')->everyTenMinutes()->withoutOverlapping();
            $schedule->command('visma:process-queue')->everyMinute()->withoutOverlapping();

            // Visma.net
            //$schedule->command('visma:fetch quick')->everyFiveMinutes();
            $schedule->command('visma:fetch daily')->dailyAt('02:00');
            $schedule->command('visma:fetch hourly')->hourly();
            $schedule->command('process-visma-deletions')->dailyAt('04:00');

            $schedule->command('wgr:fetch')->dailyAt('01:00');

            $schedule->command('articles:calculate-sales-volume')->dailyAt('06:00');
            $schedule->command('customers:calculate-sales')->dailyAt('08:00');

            $schedule->command('bestsellers:calculate')->daily();
            $schedule->command('articles:process-eol')->daily();
            $schedule->command('calculate-customer-revenue')->daily();
            $schedule->command('calculate-customer-return-rate')->daily();
            $schedule->command('calculate-article-shipping-time')->daily();

            $schedule->command('allianz:fetch-grades')->daily();
        }

        // Run only in development environment
        if (App::environment('development')) {
            $schedule->command('database:sync')->days([4]); // 4 for Thursday
        }

        // Run in all environments
        $schedule->command('todo:unreserve-old')->everyMinute();
        $schedule->command('todo:delete-tmp')->everyMinute();
        $schedule->command('todo:hold-items')->dailyAt('05:00');

        $schedule->command('api-logger:clean')->hourly();
        $schedule->command('calculate-last-article-purchase-date')->everyThreeHours();
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
