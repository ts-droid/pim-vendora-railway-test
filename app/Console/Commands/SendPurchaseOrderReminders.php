<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\PurchaseOrderEmailer;
use Illuminate\Console\Command;

class SendPurchaseOrderReminders extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting purchase order reminder evaluation.', $this->commandLogContext());

        $emailer = new PurchaseOrderEmailer();

        // TODO: Send draft reminders
        // $emailer->send('', true);


        // TODO: Send reminders for orders that have items that should have been delivered by now
        // app/Mail/PurchaseOrderReminder.php


        // TODo: Send reminders for orders that is missing uploaded invoices


        // TODO: Implement PurchaseOrderController.php::sendV2

        action_log('Finished purchase order reminder evaluation (no emails sent).', $this->commandLogContext());
    }
}
