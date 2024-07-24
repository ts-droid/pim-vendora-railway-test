<?php

namespace App\Mail;

use App\Http\Controllers\ConfigController;
use App\Models\SignDocument;
use App\Models\SignDocumentRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class DocumentSigned extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectText;
    public string $contentText;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SignDocument $document,
        public SignDocumentRecipient $recipient,
    )
    {
        $this->subjectText = ConfigController::getConfig('signed_email_subject');
        $this->contentText = nl2br(ConfigController::getConfig('signed_email_body'));

        // Replace variables
        $this->contentText = str_replace('%recipient_name%', $this->recipient->name, $this->contentText);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'esign.mail_signed',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn() => $this->document->getDocumentContent(), makeFilenameFriendly($this->document->name . '_signed') . '.pdf')
                ->withMime('application/pdf')
        ];
    }
}
