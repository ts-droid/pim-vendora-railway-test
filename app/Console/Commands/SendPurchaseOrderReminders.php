<?php

namespace App\Console\Commands;

use App\Services\PurchaseOrderEmailer;
use Illuminate\Console\Command;

class SendPurchaseOrderReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase-order:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all reminders related to purchase orders.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $emailer = new PurchaseOrderEmailer();

        // TODO: Send draft reminders
        // $emailer->send('', true);


        // TODO: Send reminders for orders that have items that should have been delivered by now
        // app/Mail/PurchaseOrderReminder.php


        // TODo: Send reminders for orders that is missing uploaded invoices


        // TODO: Implement PurchaseOrderController.php::sendV2
    }
}
