<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Models\NewsletterSubscriber;
use App\Services\MailerSend\MailerSendActivityService;
use Illuminate\Console\Command;

class CleanNewsletter extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting newsletter cleanup.', $this->commandLogContext());

        $mailerSendActivityService = new MailerSendActivityService();
        $emails = $mailerSendActivityService->getUncleanEmails();

        $deleted = NewsletterSubscriber::whereIn('email', $emails)->delete();

        action_log('Finished newsletter cleanup.', $this->commandLogContext([
            'emails_flagged' => count($emails),
            'deleted_subscribers' => $deleted,
        ]));
    }
}
