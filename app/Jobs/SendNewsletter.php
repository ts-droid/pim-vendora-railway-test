<?php

namespace App\Jobs;

use App\Models\NewsletterSubscriber;
use App\Services\MailerLiteService;
use App\Utilities\BrandPageUtility;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNewsletter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $payload
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tag = $this->payload['tag'] ?? null;
        $source = $this->payload['source'] ?? null;

        if (!$tag || !$source) {
            throw new \Exception('Tag and source is required.');
        }

        $campaignName = 'Newsletter - ' . $source . ' (' . $tag . ') - ' . date('Y-m-d H:i:s');

        $brandingData = BrandPageUtility::getBrandingData($source);

        // Load all subscribers
        $groupedSubscribers = NewsletterSubscriber::where('source', $source)
            ->where('tag', 'LIKE', '%' . $tag . '%')
            ->get()
            ->groupBy('language');

        if (!$groupedSubscribers || !$groupedSubscribers->count()) {
            return;
        }

        foreach ($groupedSubscribers as $lang => $subscribers) {
            $subject = $this->payload['subject_' . $lang] ?? null;
            $body = $this->payload['body_' . $lang] ?? null;

            if (!$subject || !$body) {
                continue; // Skip if subject or body is missing/empty
            }

            // Get/create the group
            $mailerLiteService = new MailerLiteService();
            $groupName = $campaignName . ' (' . $lang . ')';

            $group = $mailerLiteService->getGroupByName($groupName);
            if (!$group) continue;

            // Fetch or create the campaign
            $campaign = $mailerLiteService->getDraftCampaignByName($campaignName);
            if (!isset($campaign['id'])) {
                $campaign = $mailerLiteService->createCampaign([
                    'type' => 'regular',
                    'name' => $campaign['name'],
                    'emails' => [
                        [
                            'subject' => $subject,
                            'from_name' => $brandingData['brand_name'],
                            'from' => 'no-reply@vendora.se',
                            'content' => view('emails.brandPages.newsletter', [
                                'emailSubject' => $subject,
                                'emailBody' => $body,
                                'brandingData' => $brandingData,
                            ])->render(),
                        ]
                    ],
                    'groups' => [$group['id']],
                    'filter' => []
                ]);
            }

            if (empty($campaign['id'])) {
                continue; // Failed to create or fetch campaign
            }

            // Send the campaign
            $mailerLiteService->sendCampaign($campaign['id']);
        }
    }
}
