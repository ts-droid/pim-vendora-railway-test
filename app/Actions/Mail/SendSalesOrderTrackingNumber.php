<?php

namespace App\Actions\Mail;

use App\Enums\LaravelQueues;
use App\Mail\EmailLogger;
use App\Mail\RawMail;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;

class SendSalesOrderTrackingNumber
{
    public function execute(SalesOrder $salesOrder, string $trackingNumber): void
    {
        $brandingData = $salesOrder->getBrandingDate();

        App::setLocale($salesOrder->language ?: 'en');

        $emailSubject = get_phrase('tracking_number_subject');
        $emailFromEmail = 'info@vendora.se';
        $emailFromName = $brandingData['brand_name'];
        $emailBCC = ['anton@vendora.se', 'ah@vendora.se'];

        $emailBody = view('emails.salesOrder.trackingNumber', [
            'salesOrder' => $salesOrder,
            'trackingNumber' => $trackingNumber,
            'brandingData' => $brandingData,
            'emailSubject' => $emailSubject,
            'emailFromEmail' => $emailFromEmail,
            'emailFromName' => $emailFromName
        ])->render();

        $mail = (new RawMail($emailSubject, $emailBody, $emailFromEmail, $emailFromName))
            ->onQueue(LaravelQueues::MAIL->value);

        Mail::to($salesOrder->email)->bcc($emailBCC)->queue($mail);

        // Log the email
        $emailLog = EmailLogger::log(
            $salesOrder->email,
            null,
            $emailBCC,
            $emailSubject,
            $emailBody,
            null
        );

        // Log the event to the sales order
        (new SalesOrderService())->createLog($salesOrder->id, 'Sent tracking email to customer.', $emailLog->id);

        action_log('Queued sales order tracking email.', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
            'email' => $salesOrder->email,
            'tracking_number' => $trackingNumber,
            'email_log_id' => $emailLog->id,
        ]);
    }
}
