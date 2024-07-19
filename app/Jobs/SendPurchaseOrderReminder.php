<?php

namespace App\Jobs;

use App\Http\Controllers\ApiResponseController;
use App\Models\PurchaseOrder;
use App\Utilities\PurchaseOrderHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendPurchaseOrderReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PurchaseOrder $purchaseOrder,
        public Collection $orderLines,
        public ?string $emailRecipient = null
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $recipients = preg_split("/[\s,;]+/", ($this->purchaseOrder->email ?: $this->purchaseOrder->supplier->email));
        $recipients = array_map('trim', $recipients);

        if ($this->emailRecipient) {
            $recipients = [$this->emailRecipient];
        }

        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        // Validate the emails
        $recipients = array_filter($recipients, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        if (count($recipients) === 0) {
            return;
        }

        try {
            Mail::to($recipients)->send(new \App\Mail\PurchaseOrderReminder($this->purchaseOrder, $this->orderLines));
        } catch (\Exception $e) {
            log_data('Failed to send purchase order reminder. (Error: ' . $e->getMessage() . ')');
        }
    }
}
