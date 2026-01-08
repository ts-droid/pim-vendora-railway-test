<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Mail\TestEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:send-test {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a test email to the specified address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        action_log('Sending test email.', $this->commandLogContext([
            'email' => $email,
        ]));

        Mail::to($email)->send(new TestEmail());

        $this->info('Test email sent to ' . $email);

        action_log('Test email sent.', $this->commandLogContext([
            'email' => $email,
        ]));
    }
}
