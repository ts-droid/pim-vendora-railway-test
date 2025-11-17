<?php

namespace App\Mail;

use App\Http\Controllers\DoSpacesController;
use App\Models\PurchaseOrderShipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Address;

class NotifyPurchaseOrderExceptions extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public PurchaseOrderShipment $purchaseOrderShipment,
        public array $exceptions,
    )
    {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [
                new Address('logistics@vendora.se', 'Vendora Nordic AB')
            ],
            subject: 'Discrepancy in shipment: ' . $this->purchaseOrderShipment->id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.purchaseOrderException',
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
        foreach ($this->exceptions as $index => $exception) {
            foreach ($exception->images as $imageKey => $imagePath) {
                $imageContent = DoSpacesController::getContent($imagePath);

                $filename = 'exception_image_' . $index . '_' . $imageKey . '.jpg';

                $attachments[] = Attachment::fromData(
                    fn () => $imageContent,
                    $filename
                )->withMime('image/jpeg');
            }
        }

        return $attachments;
    }
}
