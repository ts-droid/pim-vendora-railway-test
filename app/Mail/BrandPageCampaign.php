<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use App\Utilities\BrandPageUtility;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BrandPageCampaign extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailSubject;
    public array $brandingData;

    /**
     * Create a new message instance.
     */
    public function __construct(NewsletterSubscriber $subscriber)
    {
        $this->emailSubject = '🖤 Black Friday – 20% off everything in our store 🖤';
        $this->brandingData = BrandPageUtility::getBrandingData($subscriber->source);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.brandPages.campaign',
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
