<?php

namespace App\Services;

use App\Enums\LaravelQueues;
use App\Mail\RawMail;
use Illuminate\Support\Facades\Mail;

class ErrorNotification
{
    const DEFAULT_RECIPIENTS = [
        'anton@scriptsector.se'
    ];

    public static function send(string $title, string $message, ?array $recipients = null)
    {
        $recipients = $recipients ?: self::DEFAULT_RECIPIENTS;

        $mail = (new RawMail($title, $message, 'noreply@vendora.se', 'Vendora PIMP'))
            ->onQueue(LaravelQueues::DEFAULT->value);

        Mail::to($recipients)->queue($mail);
    }
}
