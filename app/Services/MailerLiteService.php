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
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $this->token = env('MAILERLITE_API_TOKEN');
        $this->mailerLite = new MailerLite(['api_key' => $this->token]);
    }

    public function addSubscriber(string $email, ?string $groupName = null, ?array $group = null): ?array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->mailerLite->subscribers->create(['email' => $email]);
        $subscriber = $response['body']['data'] ?? null;

        if (!$subscriber) return null;

        if ($groupName || $group) {
            $group = $group ?: $this->getGroupByName($groupName);

            if ($group) {
                $this->mailerLite->groups->assignSubscriber($group['id'], $subscriber['id']);
            }
        }

        return $subscriber;
    }

    public function getGroupByName(string $groupName): ?array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->mailerLite->groups->get();
        $groups = $response['body']['data'] ?? [];

        foreach ($groups as $group) {
            if ($group['name'] != $groupName) continue;

            return $group;
        }

        $response = $this->mailerLite->groups->create(['name' => $groupName]);
        return $response['body']['data'] ?? null;
    }

    public function getDraftCampaignByName(string $name): ?array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->mailerLite->campaigns->get([
            'filter' => ['status' => 'draft'],
            'limit' => 100
        ]);

        $campaigns = $response['body']['data'] ?? [];
        foreach ($campaigns as $campaign) {
            if ($campaign['name'] == $name) {
                return $campaign;
            }
        }

        return null;
    }

    public function createCampaign(array $data): ?array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->mailerLite->campaigns->create($data);
        return $response['body']['data'] ?? null;
    }

    public function sendCampaign(string $campaignId): ?array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->mailerLite->campaigns->schedule($campaignId, [
            'delivery' => 'instant',
        ]);

        return $response['body']['data'] ?? null;
    }
}
