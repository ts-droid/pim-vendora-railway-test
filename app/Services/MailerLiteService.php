<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use MailerLite\MailerLite;

class MailerLiteService
{
    protected string $token;

    protected MailerLite $mailerLite;

    public function __construct()
    {
        $this->token = env('MAILERLITE_API_TOKEN');
        $this->mailerLite = new MailerLite(['api_key' => $this->token]);
    }

    public function addSubscriber(string $email)
    {
        $response = $this->mailerLite->subscribers->create(['email' => $email]);
    }
}
