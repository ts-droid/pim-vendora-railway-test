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

class DocumentSign extends Mailable
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
        $this->subjectText = ConfigController::getConfig('signing_email_subject');
        $this->contentText = nl2br(ConfigController::getConfig('signing_email_body'));

        // Replace variables
        $documentLink = route('esign.document', ['document' => $this->document->id, 'secret' => $this->recipient->access_key]);

        $this->contentText = str_replace('%recipient_name%', $this->recipient->name, $this->contentText);
        $this->contentText = str_replace('%sign_link%', '<a href="' . $documentLink . '" target="_blank">View and sign document here</a>', $this->contentText);
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
            view: 'esign.mail',
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
