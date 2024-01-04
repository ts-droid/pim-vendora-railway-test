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

class PurchaseOrderReminder extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailSubject;

    public string $emailBody;

    public array $orderLineIDs;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public \App\Models\PurchaseOrder $purchaseOrder,
        public \Illuminate\Support\Collection $orderLines,
    )
    {
        $this->orderLineIDs = $orderLines->pluck('id')->toArray();

        $orderLineIDs = $orderLines->pluck('id')->toArray();

        $this->emailSubject = ConfigController::getConfig('purchase_system_reminder_email_subject');
        $this->emailBody = ConfigController::getConfig('purchase_system_reminder_email_body');

        // Replace variables
        $this->emailSubject = str_replace('{supplier_name}', $purchaseOrder->supplier_name, $this->emailSubject);
        $this->emailBody = str_replace('{supplier_name}', $purchaseOrder->supplier_name, $this->emailBody);

        $this->emailSubject = str_replace('{order_number}', $purchaseOrder->order_number, $this->emailSubject);
        $this->emailBody = str_replace('{order_number}', $purchaseOrder->order_number, $this->emailBody);

        $this->emailSubject = str_replace('{order_date}', $purchaseOrder->date, $this->emailSubject);
        $this->emailBody = str_replace('{order_date}', $purchaseOrder->date, $this->emailBody);

        $this->emailBody = str_replace('{details_link}', '<a href="' . route('purchaseOrder.eta', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash(), 'orderLines' => implode(',', $orderLineIDs)]) . '" target="_blank">Provide delivery dates here</a>', $this->emailBody);
        $this->emailBody = str_replace('{order_table}', view('purchaseOrders.partials.reminderTable', compact('orderLines'))->render(), $this->emailBody);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $senderEmail = ConfigController::getConfig('purchase_system_reminder_email', '');

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
            view: 'emails.purchaseOrderReminder',
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
