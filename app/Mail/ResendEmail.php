<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class ResendEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $body;
    public string $subjectText;
    public array $attachmentsList;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subjectText, string $body, array $attachmentsList = [])
    {
        $this->subjectText = $subjectText;
        $this->body = $body;
        $this->attachmentsList = $attachmentsList;
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
            view: 'emails.resend',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return collect($this->attachmentsList)
            ->map(function ($filePath) {
                return Attachment::fromPath(storage_path('app/' . $filePath));
            })
            ->all();
    }
}
