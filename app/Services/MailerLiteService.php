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

    public function addSubscriber(string $email, ?string $groupName = null): ?array
    {
        $response = $this->mailerLite->subscribers->create(['email' => $email]);
        $subscriber = $response['body']['data'] ?? null;

        if (!$subscriber) return null;

        if ($groupName) {
            $group = $this->getGroupByName($groupName);
            if ($group) {
                $this->mailerLite->groups->assignSubscriber($group['id'], $subscriber['id']);
            }
        }

        return $subscriber;
    }

    public function getGroupByName(string $groupName): ?array
    {
        $response = $this->mailerLite->groups->get();
        $groups = $response['body']['data'] ?? [];

        foreach ($groups as $group) {
            if ($group['name'] != $groupName) continue;

            return $group;
        }

        $response = $this->mailerLite->groups->create(['name' => $groupName]);
        return $response['body']['data'] ?? null;
    }
}
