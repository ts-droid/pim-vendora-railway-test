<?php

namespace App\Mail;

use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class SalesOrderTrackingNumber extends Mailable
{
    use Queueable, SerializesModels;

    public SalesOrder $salesOrder;
    public array $brandingData;

    public string $trackingNumber;

    public string $emailSubject;
    public string $emailFromEmail;
    public string $emailFromName;

    /**
     * Create a new message instance.
     */
    public function __construct(SalesOrder $salesOrder, string $trackingNumber)
    {
        $this->trackingNumber = $trackingNumber;

        $this->salesOrder = $salesOrder;
        $this->brandingData = $salesOrder->getBrandingDate();

        App::setLocale($salesOrder->language ?: 'en');

        $this->emailSubject = __('tracking_number_subject');
        $this->emailFromEmail = 'info@vendora.se';
        $this->emailFromName = $this->brandingData['brand_name'];

        $salesOrderService = new SalesOrderService();
        $salesOrderService->createLog($this->salesOrder->id, 'Sent tracking email to customer.');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->emailFromEmail, $this->emailFromName),
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.salesOrder.trackingNumber',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
