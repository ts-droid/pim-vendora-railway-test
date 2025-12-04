<?php

namespace App\Services;

use App\Enums\LaravelQueues;
use App\Mail\RawMail;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public static function sendMail(string $subject, string $body, string|array $to = null): void
    {
        $recipients = $to ?? config('app.developer_emails', []);
        if (is_string($recipients)) {
            $recipients = [$recipients];
        }

        $mail = (new RawMail($subject, $body, 'noreply@vendora.se', 'Vendora PIMP'))
            ->onQueue(LaravelQueues::MAIL->value);

        Mail::to($recipients)->queue($mail);
    }

}
