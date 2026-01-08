<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\SalesVolumeCalculator;
use Illuminate\Console\Command;

class CalculateArticleSalesVolume extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:calculate-sales-volume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the sales volume for each article.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting article sales volume calculation.', $this->commandLogContext());

        $calculator = new SalesVolumeCalculator();
        $calculator->calculateTotalSales();
        $calculator->calculateArticles();

        action_log('Finished article sales volume calculation.', $this->commandLogContext());
    }
}
