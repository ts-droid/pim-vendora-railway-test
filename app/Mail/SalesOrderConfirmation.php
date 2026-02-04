<?php

namespace App\Mail;

use App\Models\SalesOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class SalesOrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public SalesOrder $salesOrder;
    public array $brandingData;
    public string $emailSubject;
    public string $emailFromEmail;
    public string $emailFromName;
    public bool $hasShipping;

    /**
     * Create a new message instance.
     */
    public function __construct(
        SalesOrder $salesOrder,
        array $brandingData,
        string $subject,
        string $fromEmail,
        string $fromName,
        bool $hasShipping
    ) {
        $this->salesOrder = $salesOrder;
        $this->brandingData = $brandingData;
        $this->emailSubject = $subject;
        $this->emailFromEmail = $fromEmail;
        $this->emailFromName = $fromName;
        $this->hasShipping = $hasShipping;
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
            view: 'emails.salesOrder.confirmation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $receiptPdf = Pdf::loadView('emails.salesOrder.receiptPdf', [
            'salesOrder' => $this->salesOrder,
            'brandingData' => $this->brandingData,
        ]);

        return [
            Attachment::fromData(
                fn () => $receiptPdf->output(),
                'receipt.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
