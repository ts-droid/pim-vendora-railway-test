<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyFailedJobs extends Command
{
    use ProvidesCommandLogContext;

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

        action_log('Checking failed jobs for notification.', $this->commandLogContext([
            'date' => $date,
        ]));

        $failedJobs = DB::table('failed_jobs')->whereBetween('failed_at', [$startDate, $endDate])->count();

        if (!$failedJobs) {
            action_log('No failed jobs found for notification.', $this->commandLogContext([
                'date' => $date,
            ]));
            return;
        }

        NotificationService::sendMail(
            'Failed jobs notification',
            'Good morning! Yesterday (' . $date . ') there were ' . $failedJobs . ' failed jobs in the system. Please check the failed jobs table for more details. https://api.vendora.se/job-monitor'
        );

        action_log('Sent failed jobs notification.', $this->commandLogContext([
            'date' => $date,
            'failed_jobs' => $failedJobs,
        ]));
    }
}
