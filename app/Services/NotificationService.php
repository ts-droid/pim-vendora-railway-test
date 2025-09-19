<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class NotificationService
{

    public static function sendMail(string $subject, string $body, string|array $to = null): void
    {
        $recipients = $to ?? config('notifications.admin_emails', []);

        if (is_string($recipients)) {
            $recipients = [$recipients];
        }

        foreach ($recipients as $email) {
            Mail::raw($body, function($message) use ($subject, $email) {
                $message->to($email)->subject($subject);
            });
        }
    }

}
