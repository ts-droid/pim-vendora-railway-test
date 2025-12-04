<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyFailedJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify-failed-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a notification about failed jobs.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = date('Y-m-d', strtotime('-1 day'));
        $startDate = $date . ' 00:00:00';
        $endDate = $date . ' 23:59:59';

        $failedJobs = DB::table('failed_jobs')->whereBetween('failed_at', [$startDate, $endDate])->count();

        if (!$failedJobs) return;

        NotificationService::sendMail(
            'Failed jobs notification',
            'Good morning! Yesterday (' . $date . ') there were ' . $failedJobs . ' failed jobs in the system. Please check the failed jobs table for more details. https://api.vendora.se/job-monitor'
        );
    }
}
