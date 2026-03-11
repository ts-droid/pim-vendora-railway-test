<?php

namespace App\Services;

use App\Enums\LaravelQueues;
use App\Mail\PurchaseOrderCancellation;
use App\Mail\PurchaseOrderConfirmReminder;
use App\Mail\PurchaseOrderInvoiceReminder;
use App\Mail\PurchaseOrderReminder;
use App\Mail\PurchaseOrderRowCancellation;
use App\Mail\PurchaseOrderShippingReminder;
use App\Mail\SendPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Utilities\PurchaseOrderHelper;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderEmailer
{
    public function sendNewOrder(PurchaseOrder $purchaseOrder)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Get recipients
        $recipients = $this->getPurchaseOrderRecipients($purchaseOrder);
        if (count($recipients) === 0) {
            return [
                'success' => false,
                'message' => 'No valid recipient email addresses found'
            ];
        }

        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        // Dispatch the email
        try {
            Mail::to($recipients)->queue((new SendPurchaseOrder($purchaseOrder))->onQueue(LaravelQueues::MAIL->value));
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

    public function sendConfirmReminder(PurchaseOrder $purchaseOrder)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Get recipients
        $recipients = $this->getPurchaseOrderRecipients($purchaseOrder);
        if (count($recipients) === 0) {
            return [
                'success' => false,
                'message' => 'No valid recipient email addresses found'
            ];
        }

        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        // Dispatch the email
        try {
            Mail::to($recipients)->queue((new PurchaseOrderConfirmReminder($purchaseOrder))->onQueue(LaravelQueues::MAIL->value));
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

    public function sendShippingReminder(PurchaseOrder $purchaseOrder)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Get recipients
        $recipients = $this->getPurchaseOrderRecipients($purchaseOrder);
        if (count($recipients) === 0) {
            return [
                'success' => false,
                'message' => 'No valid recipient email addresses found'
            ];
        }

        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        // Dispatch the email
        try {
            Mail::to($recipients)->queue((new PurchaseOrderShippingReminder($purchaseOrder))->onQueue(LaravelQueues::MAIL->value));
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

    public function sendInvoiceReminder(PurchaseOrder $purchaseOrder)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Get recipients
        $recipients = $this->getPurchaseOrderRecipients($purchaseOrder);
        if (count($recipients) === 0) {
            return [
                'success' => false,
                'message' => 'No valid recipient email addresses found'
            ];
        }

        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        // Dispatch the email
        try {
            Mail::to($recipients)->queue((new PurchaseOrderInvoiceReminder($purchaseOrder))->onQueue(LaravelQueues::MAIL->value));
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

    public function sendETDReminder(int $purchaseOrderID, array $purchaseOrderLines)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $purchaseOrder = PurchaseOrder::find($purchaseOrderID);
        if (!$purchaseOrder) {
            return [
                'success' => false,
                'message' => 'Purchase order not found'
            ];
        }

        // Get recipients
        $recipients = $this->getPurchaseOrderRecipients($purchaseOrder);
        if (count($recipients) === 0) {
            return [
                'success' => false,
                'message' => 'No valid recipient email addresses found'
            ];
        }

        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        // Dispatch the email
        try {
            Mail::to($recipients)->queue((new PurchaseOrderReminder($purchaseOrder))->onQueue(LaravelQueues::MAIL->value));
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
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

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
            Mail::to($recipients)->queue((new PurchaseOrderRowCancellation($purchaseOrder, $purchaseOrderLine))->onQueue(LaravelQueues::MAIL->value));
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
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

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
            Mail::to($recipients)->queue((new PurchaseOrderCancellation($purchaseOrder))->onQueue(LaravelQueues::MAIL->value));
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

    private function getPurchaseOrderRecipients(PurchaseOrder $purchaseOrder): array
    {
        $email = $purchaseOrder->email
            ?: $purchaseOrder->supplier->supplier_contact_email
                ?: $purchaseOrder->supplier->main_contact_email
                    ?: $purchaseOrder->supplier->email;

        return $this->getRecipients($email);
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
