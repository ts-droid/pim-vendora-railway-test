<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
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
        $subscribers = NewsletterSubscriber::all()->groupBy('source');

        $mailersend = new MailerSend(['api_key' => env('MAILERSEND_API_TOKEN')]);

        foreach ($subscribers as $source => $items) {
            try {
                $brandingData = BrandPageUtility::getBrandingData($source);
                if (!$brandingData['is_brand']) continue;
            } catch (\Throwable $e) {
                continue;
            }

            $subject = '🖤 Black Friday – 20% off everything in our store 🖤';

            // Send in chunks of 50
            $chunks = $items->chunk(50);
            foreach ($chunks as $chunk) {

                $recipients = [];
                foreach ($chunk as $item) {
                    $recipients[] = new Recipient($item->email, null);
                }

                $bulkEmailParams = [];

                $bulkEmailParams[] = (new EmailParams())
                    ->setFrom('noreply@vendora.se')
                    ->setFromName($brandingData['brand_name'])
                    ->setRecipients($recipients)
                    ->setSubject($subject)
                    ->setHtml(view('emails.brandPages.campaign', ['emailSubject' => $subject, 'brandingData' => $brandingData])->render());

                $response = $mailersend->bulkEmail->send($bulkEmailParams);
                dump($response);

                sleep(10);

            }
        }
    }
}
