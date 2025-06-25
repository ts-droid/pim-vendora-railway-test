<?php

namespace App\Mail;

use App\Models\Email;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email as SymfonyEmail;

class EmailLogger
{
    public static function log(string|array $to, string|array|null $cc, string|array|null $bcc, string $subject, string $body, ?array $attachments): Email
    {
        if (is_array($to)) {
            $to = implode(',', $to);
        }

        if (is_array($cc)) {
            $cc = implode(',', $cc);
        }

        if (is_array($bcc)) {
            $bcc = implode(',', $bcc);
        }

        return Email::create([
            'to' => (string) $to,
            'cc' => (string) $cc,
            'bcc' => (string) $bcc,
            'subject' => $subject,
            'body' => $body,
            'attachments' => $attachments,
        ]);
    }
}
