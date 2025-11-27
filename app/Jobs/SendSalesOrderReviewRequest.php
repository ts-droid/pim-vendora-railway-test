<?php

namespace App\Jobs;

use App\Enums\LaravelQueues;
use App\Mail\EmailLogger;
use App\Mail\RawMail;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;

class SendSalesOrderReviewRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public SalesOrder $salesOrder;

    /**
     * Create a new job instance.
     */
    public function __construct(SalesOrder $salesOrder)
    {
        $this->salesOrder = $salesOrder;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $brandingData = $this->salesOrder->getBrandingDate();

        App::setLocale($this->salesOrder->language ?: 'en');

        $emailSubject = __('request_review_subject');
        $emailFromEmail = 'info@vendora.se';
        $emailFromName = $brandingData['brand_name'];
        $emailBCC = ['anton@vendora.se', 'ah@vendora.se'];

        $emailBody = view('emails.salesOrder.requestReview', [
            'salesOrder' => $this->salesOrder,
            'brandingData' => $brandingData,
            'emailSubject' => $emailSubject,
            'emailFromEmail' => $emailFromEmail,
            'emailFromName' => $emailFromName
        ])->render();

        $mail = (new RawMail($emailSubject, $emailBody, $emailFromEmail, $emailFromName))
            ->onQueue(LaravelQueues::MAIL->value);

        Mail::to($this->salesOrder->email)->bcc($emailBCC)->queue($mail);

        $emailLog = EmailLogger::log(
            $this->salesOrder->email,
            null,
            $emailBCC,
            $emailSubject,
            $emailBody,
            null
        );

        // Log the event to the sales order
        (new SalesOrderService())->createLog($this->salesOrder->id, 'Sent email to customer to request review.', $emailLog->id);
    }
}
