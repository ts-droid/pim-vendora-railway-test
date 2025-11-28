<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use App\Services\MailerLiteService;
use App\Utilities\BrandPageUtility;
use Illuminate\Console\Command;
use MailerSend\MailerSend;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\Helpers\Builder\EmailParams;

class SendBrandPageCampaign extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brand-page:send-campaign';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send brand page campaign';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Settings
        $baseCampaignName = 'Black Friday 2025';

        $mailerLiteService = new MailerLiteService();


        $subscribers = NewsletterSubscriber::all()->groupBy('source');

        foreach ($subscribers as $source => $items) {
            $campaignName = $baseCampaignName . ' - ' . $source;
            $subject = '🖤 Black Friday – 20% off everything in our store 🖤';

            try {
                $brandingData = BrandPageUtility::getBrandingData($source);
                if (!$brandingData['is_brand']) continue;
            } catch (\Throwable $e) {
                continue;
            }

            // Fetch the group
            $group = $mailerLiteService->getGroupByName($source);
            if (!$group) continue; // Failed to fetch or create group

            // Fetch or create the campaign
            $campaign = $mailerLiteService->getDraftCampaignByName($campaignName);
            if (!isset($campaign['id'])) {
                $campaign = $mailerLiteService->createCampaign([
                    'type' => 'regular',
                    'name' => $campaignName,
                    'emails' => [
                        [
                            'subject' => $subject,
                            'from_name' => $brandingData['brand_name'],
                            'from' => 'no-reply@vendora.se',
                            'content' => view('emails.brandPages.campaign', ['emailSubject' => $subject, 'brandingData' => $brandingData])->render(),
                            'groups' => [$group['id']]
                        ]
                    ],
                    'filter' => [],
                ]);
            }

            if (!$campaign) continue; // Failed to create or fetch campaign


            // TODO: Send the campaign
        }
    }
}
