<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderEmailer
{
    public function send(PurchaseOrder $purchaseOrder, bool $isReminder = false)
    {
        $recipients = [$purchaseOrder->email ?: ($purchaseOrder->supplier->email ?? null)];

        $recipients[] = 'ts@vendora.se';

        // Validate the emails
        $recipients = array_filter($recipients, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        // Make sure we have at least 1 email address
        if (count($recipients) === 0) {
            return [false, 'No valid recipient email addresses found.'];
        }

        // Disptach the email
        try {
            Mail::to($recipients)->queue(new \App\Mail\PurchaseOrder($purchaseOrder, $isReminder));
        }
        catch (\Exception $e) {
            return [false, $e->getMessage()];
        }

        $purchaseOrder->update(['is_sent' => 1]);

        return [true, 'Email queued successfully.'];
    }
}
