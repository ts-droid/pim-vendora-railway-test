<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use Illuminate\Console\Command;

class CalculateArticleShippingTime extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate-article-shipping-time';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates the average shipping time in days from supplier to vendora.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting article shipping time calculation.', $this->commandLogContext());

        $job = new \App\Jobs\CalculateArticleShippingTime();
        $job->handle();

        action_log('Finished article shipping time calculation.', $this->commandLogContext([
            'job_class' => get_class($job),
        ]));
    }
}
