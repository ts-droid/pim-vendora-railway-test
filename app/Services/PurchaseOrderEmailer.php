<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Utilities\PurchaseOrderHelper;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderEmailer
{
    public function send(PurchaseOrder $purchaseOrder, bool $isReminder = false)
    {
        $recipients = preg_split("/[\s,;]+/", ($purchaseOrder->email ?: ($purchaseOrder->supplier->email ?? '')));
        $recipients = array_map('trim', $recipients);

        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

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
