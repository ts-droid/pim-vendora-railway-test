<?php

namespace App\Console\Commands;

use App\Services\PurchaseOrderReminderService;
use Illuminate\Console\Command;

class SendPurchaseReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase-orders:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send purchase order reminders to suppliers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $reminderService = new PurchaseOrderReminderService();
        $reminderService->remindDrafts();
    }
}
