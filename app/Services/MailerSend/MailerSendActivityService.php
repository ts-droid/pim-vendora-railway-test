<?php

namespace App\Services\MailerSend;

class MailerSendActivityService extends MailerSendApiService
{
    public function getUncleanEmails(): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $activities = $this->getPages('GET', '/v1/activity/' . $this->domainId, [
            'limit' => 100,
            'date_from' => strtotime('-5 days'),
            'date_to' => time(),
            'event' => ['soft_bounced', 'hard_bounced', 'deferred', 'unsubscribed', 'spam_complaints']
        ]);

        $emails = [];
        foreach ($activities as $activity) {
            $emails[] = $activity['email']['recipient']['email'];
        }

        return array_unique($emails);
    }
}
