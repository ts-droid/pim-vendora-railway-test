<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Models\NewsletterSubscriber;
use App\Services\MailerLiteService;
use App\Utilities\BrandPageUtility;
use Illuminate\Console\Command;
use MailerSend\MailerSend;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\Helpers\Builder\EmailParams;

class SendBrandPageCampaign extends Command
{
    use ProvidesCommandLogContext;

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
        $baseCampaignName = 'Black Friday 2025 (2)';

        action_log('Starting brand page campaign processing.', $this->commandLogContext([
            'base_campaign_name' => $baseCampaignName,
        ]));

        $mailerLiteService = new MailerLiteService();


        $subscribers = NewsletterSubscriber::all()->groupBy('source');
        $processedSources = 0;
        $campaignsCreated = 0;

        foreach ($subscribers as $source => $items) {
            $campaignName = $baseCampaignName . ' - ' . $source;
            $subject = '🖤 Black Friday – 20% off everything in our store 🖤';

            try {
                $brandingData = BrandPageUtility::getBrandingData($source);
                if (!$brandingData['is_brand']) continue;
            } catch (\Throwable $e) {
                action_log('Failed to fetch branding data for brand page campaign.', $this->commandLogContext([
                    'source' => $source,
                    'error' => $e->getMessage(),
                ]), 'warning');
                continue;
            }

            // Fetch the group
            $group = $mailerLiteService->getGroupByName($source);
            if (!$group) {
                action_log('Missing MailerLite group for brand page campaign.', $this->commandLogContext([
                    'source' => $source,
                ]), 'warning');
                continue; // Failed to fetch or create group
            }

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
                        ]
                    ],
                    'groups' => [$group['id']],
                    'filter' => [],
                ]);

                if (isset($campaign['id'])) {
                    $campaignsCreated++;
                }
            }

            if (!$campaign) {
                action_log('Failed to create or fetch brand page campaign.', $this->commandLogContext([
                    'source' => $source,
                    'campaign_name' => $campaignName,
                ]), 'warning');
                continue; // Failed to create or fetch campaign
            }

            $processedSources++;


            // TODO: Send the campaign
        }

        action_log('Finished brand page campaign processing.', $this->commandLogContext([
            'sources_found' => $subscribers->count(),
            'sources_ready' => $processedSources,
            'campaigns_created' => $campaignsCreated,
        ]));
    }
}
