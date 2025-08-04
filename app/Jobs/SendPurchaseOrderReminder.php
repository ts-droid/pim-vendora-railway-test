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
        if ($this->emailRecipient) {
            $recipients = $this->emailRecipient; // Use the provided email recipient
        }
        elseif ($this->purchaseOrder->supplier->po_contact_email ?? null) {
            $recipients = $this->purchaseOrder->supplier->po_contact_email; // Use the supplier's reminder email
        }
        elseif ($this->purchaseOrder->email) {
            $recipients = $this->purchaseOrder->email; // Use the purchase order's email
        }
        else {
            $recipients = $this->purchaseOrder->supplier->email ?? ''; // Use the supplier's email
        }

        $recipients = preg_split("/[\s,;]+/", $recipients);
        $recipients = array_map('trim', $recipients);

        // Validate the emails
        $recipients = array_filter($recipients, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        if (count($recipients) === 0) {
            return;
        }

        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        try {
            Mail::to($recipients)->send(new \App\Mail\PurchaseOrderReminder($this->purchaseOrder, $this->orderLines));
        } catch (\Exception $e) {
            log_data('Failed to send purchase order reminder. (Error: ' . $e->getMessage() . ')');
        }
    }
}
