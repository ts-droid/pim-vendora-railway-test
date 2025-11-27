<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use App\Services\MailerSend\MailerSendActivityService;
use Illuminate\Console\Command;

class CleanNewsletter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletter:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean newsletter list.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mailerSendActivityService = new MailerSendActivityService();
        $emails = $mailerSendActivityService->getUncleanEmails();

        NewsletterSubscriber::whereIn('email', $emails)->delete();
    }
}
