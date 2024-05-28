<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CalculateArticleShippingTime extends Command
{
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
        $job = new \App\Jobs\CalculateArticleShippingTime();
        $job->handle();
    }
}
