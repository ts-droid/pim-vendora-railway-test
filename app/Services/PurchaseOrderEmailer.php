<?php

namespace App\Services;

use App\Mail\PurchaseOrderCancellation;
use App\Mail\PurchaseOrderRowCancellation;
use App\Mail\SendPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Utilities\PurchaseOrderHelper;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderEmailer
{
    public function send(PurchaseOrder $purchaseOrder, bool $isReminder = false)
    {
        $recipients = preg_split("/[\s,;]+/", ($purchaseOrder->email ?: ($purchaseOrder->supplier->email ?? '')));
        $recipients = array_map('trim', $recipients);

        // Validate the emails
        $recipients = array_filter($recipients, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        // Make sure we have at least 1 email address
        if (count($recipients) === 0) {
            return [false, 'No valid recipient email addresses found.'];
        }

        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        // Dispatch the email
        try {
            if ($isReminder) {
                Mail::to($recipients)->queue(new \App\Mail\PurchaseOrderConfirmReminder($purchaseOrder));
            }
            else {
                Mail::to($recipients)->queue(new \App\Mail\PurchaseOrder($purchaseOrder));
            }
        }
        catch (\Exception $e) {
            return [false, $e->getMessage()];
        }

        return [true, 'Email queued successfully.'];
    }




    public function sendNewOrder(PurchaseOrder $purchaseOrder)
    {
        // Load the recipients for the purchase order
        $email = $purchaseOrder->email
            ?: $purchaseOrder->supplier->supplier_contact_email
            ?: $purchaseOrder->supplier->main_contact_email
            ?: $purchaseOrder->supplier->email;

        $recipients = $this->getRecipients($email);

        if (count($recipients) === 0) {
            return [
                'success' => false,
                'message' => 'No valid recipient email addresses found'
            ];
        }

        // Add CC recipients
        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        // Dispatch the email
        try {
            Mail::to($recipients)->queue(new SendPurchaseOrder($purchaseOrder));
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        return [
            'success' => true,
            'message' => 'Email queued successfully'
        ];
    }

    public function sendCancelRow(PurchaseOrder $purchaseOrder, PurchaseOrderLine $purchaseOrderLine)
    {
        $email = $purchaseOrder->email
            ?: $purchaseOrder->supplier->supplier_contact_email
            ?: $purchaseOrder->supplier->main_contact_email
            ?: $purchaseOrder->supplier->email;

        $recipients = $this->getRecipients($email);

        if (count($recipients) === 0) {
            return [
                'success' => false,
                'message' => 'No valid recipient email addresses found'
            ];
        }

        // Dispatch the email
        try {
            Mail::to($recipients)->queue(new PurchaseOrderRowCancellation($purchaseOrder, $purchaseOrderLine));
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        return [
            'success' => true,
            'message' => 'Email queued successfully'
        ];
    }

    public function sendCancelOrder(PurchaseOrder $purchaseOrder)
    {
        $email = $purchaseOrder->email
            ?: $purchaseOrder->supplier->supplier_contact_email
            ?: $purchaseOrder->supplier->main_contact_email
            ?: $purchaseOrder->supplier->email;

        $recipients = $this->getRecipients($email);

        if (count($recipients) === 0) {
            return [
                'success' => false,
                'message' => 'No valid recipient email addresses found'
            ];
        }

        // Add CC recipients
        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        // Dispatch the email
        try {
            Mail::to($recipients)->queue(new PurchaseOrderCancellation($purchaseOrder));
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        return [
            'success' => true,
            'message' => 'Email queued successfully'
        ];
    }

    private function getRecipients(string $recipients): array
    {
        $recipients = preg_split("/[\s,;]+/", $recipients);
        $recipients = array_map('trim', $recipients);

        $recipients = array_filter($recipients, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        return $recipients;
    }
}
