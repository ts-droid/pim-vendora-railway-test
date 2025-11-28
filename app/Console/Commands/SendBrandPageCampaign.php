<?php

namespace App\Console\Commands;

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
        $subject = '🖤 Black Friday – 20% off everything in our store 🖤';
        $brandingData = BrandPageUtility::getBrandingData('satechi.se');

        $mailersend = new MailerSend(['api_key' => env('MAILERSEND_API_TOKEN')]);

        $recipients = [
            new Recipient('anton@scriptsector.se', null),
            new Recipient('anton@vendora.se', null),
        ];

        $bulkEmailParams = [];

        $bulkEmailParams[] = (new EmailParams())
            ->setFrom('noreply@vendora.se.com')
            ->setFromName($brandingData['brand_name'])
            ->setRecipients($recipients)
            ->setSubject($subject)
            ->setHtml(view('emails.brandPages.campaign', ['emailSubject' => $subject, 'brandingData' => $brandingData])->render());

        $mailersend->bulkEmail->send($bulkEmailParams);
    }
}
