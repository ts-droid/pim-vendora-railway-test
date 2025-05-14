<?php

namespace App\Mail;

use App\Models\Email;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email as SymfonyEmail;

class EmailLogger
{
    public static function log(string $to, ?string $cc, ?string $bcc, string $subject, string $body, ?array $attachments): Email
    {
        return Email::create([
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $subject,
            'body' => $body,
            'attachments' => $attachments,
        ]);
    }
}
