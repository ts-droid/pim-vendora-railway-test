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

    public string $emailSubject;

    public string $emailBody;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public \App\Models\PurchaseOrder $purchaseOrder,
    )
    {
        $this->emailSubject = ConfigController::getConfig('purchase_system_new_order_email_subject');
        $this->emailBody = ConfigController::getConfig('purchase_system_new_order_email_body');

        // Replace variables
        $this->emailSubject = str_replace('{order_number}', $purchaseOrder->order_number, $this->emailSubject);
        $this->emailBody = str_replace('{order_number}', $purchaseOrder->order_number, $this->emailBody);

        $this->emailSubject = str_replace('{order_date}', $purchaseOrder->date, $this->emailSubject);
        $this->emailBody = str_replace('{order_date}', $purchaseOrder->date, $this->emailBody);

        $this->emailSubject = str_replace('{payment_terms}', ($purchaseOrder->supplier->credit_terms_description ?? ''), $this->emailSubject);
        $this->emailBody = str_replace('{payment_terms}', ($purchaseOrder->supplier->credit_terms_description ?? ''), $this->emailBody);

        // Replace some words
        $this->emailSubject = str_replace('dagar', 'days', $this->emailSubject);
        $this->emailBody = str_replace('dagar', 'days', $this->emailBody);

        $this->emailBody = str_replace('{confirm_link}', '<a href="' . route('purchaseOrder.confirm', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) . '" target="_blank">Confirm the order here</a>', $this->emailBody);
        $this->emailBody = str_replace('{order_table}', view('purchaseOrders.partials.orderTable', compact('purchaseOrder'))->render(), $this->emailBody);
    }

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
            subject: $this->emailSubject,
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
        return [];
    }
}
