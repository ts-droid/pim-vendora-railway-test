<?php

namespace App\Actions\Mail;

use App\Enums\LaravelQueues;
use App\Mail\EmailLogger;
use App\Mail\RawMail;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;

class SendSalesOrderConfirmation
{
    public function execute(SalesOrder $salesOrder): void
    {
        $brandingData = $salesOrder->getBrandingDate();

        App::setLocale($salesOrder->language ?: 'en');

        $emailSubject = __('order_confirm_subject');
        $emailFromEmail = 'info@vendora.se';
        $emailFromName = $brandingData['brand_name'];
        $emailBCC = 'anton@vendora.se';

        $emailBody = view('emails.salesOrder.confirmation', [
            'salesOrder' => $salesOrder,
            'brandingData' => $brandingData,
            'emailSubject' => $emailSubject,
            'emailFromEmail' => $emailFromEmail,
            'emailFromName' => $emailFromName
        ])->render();

        $mail = (new RawMail($emailSubject, $emailBody, $emailFromEmail, $emailFromName))
            ->onQueue(LaravelQueues::DEFAULT->value);

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
        (new SalesOrderService())->createLog($salesOrder->id, 'Sent order confirmation email.', $emailLog->id);
    }


}
