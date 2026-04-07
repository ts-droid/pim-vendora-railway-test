<?php

namespace App\Actions\Mail;

use App\Enums\LaravelQueues;
use App\Mail\EmailLogger;
use App\Mail\RawMail;
use App\Mail\SalesOrderConfirmation;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendSalesOrderConfirmation
{
    public function execute(SalesOrder $salesOrder): void
    {
        $brandingData = $salesOrder->getBrandingDate();

        App::setLocale($salesOrder->language ?: 'en');

        $emailSubject = get_phrase('order_confirm_subject');
        $emailFromEmail = 'info@vendora.se';
        $emailFromName = $brandingData['brand_name'];
        $emailBCC = ['anton@vendora.se', 'ah@vendora.se'];

        $hasShipping = $salesOrder->orderHasShipping();

        $mail = (new SalesOrderConfirmation(
            $salesOrder,
            $brandingData,
            $emailSubject,
            $emailFromEmail,
            $emailFromName,
            $hasShipping,
        ))->onQueue(LaravelQueues::MAIL->value);

        Mail::to($salesOrder->email)->bcc($emailBCC)->queue($mail);

        $emailBody = view('emails.salesOrder.confirmation', [
            'salesOrder' => $salesOrder,
            'brandingData' => $brandingData,
            'emailSubject' => $emailSubject,
            'emailFromEmail' => $emailFromEmail,
            'emailFromName' => $emailFromName,
            'hasShipping' => $hasShipping
        ])->render();

        // Log the email
        $emailLog = EmailLogger::log(
            $salesOrder->email,
            null,
            $emailBCC,
            $emailSubject,
            $emailBody,
            null,
            $emailFromName,
            $emailFromEmail
        );

        // Log the event to the sales order
        (new SalesOrderService())->createLog($salesOrder->id, 'Sent order confirmation email.', $emailLog->id);

        action_log('Queued sales order confirmation email.', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
            'email' => $salesOrder->email,
            'email_log_id' => $emailLog->id,
        ]);
    }


}
