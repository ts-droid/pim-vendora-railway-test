<?php

namespace App\Mail;

use App\Http\Controllers\ConfigController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public \App\Models\PurchaseOrder $purchaseOrder,
        public ?string $pdfContent = null,
    )
    {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $senderEmail = ConfigController::getConfig('purchase_system_order_email', '');

        return new Envelope(
            from: new Address($senderEmail, 'Vendora Nordic AB'),
            replyTo: [
                new Address($senderEmail, 'Vendora Nordic AB'),
            ],
            subject: 'Purchase Order',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.purchaseOrder',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->pdfContent) {
            $attachments[] = $this->attachData($this->pdfContent, 'purchase_order.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        return $attachments;
    }
}
