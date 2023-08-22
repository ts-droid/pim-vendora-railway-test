<?php

namespace App\Console\Commands;

use App\Http\Controllers\ArticleController;
use Illuminate\Console\Command;

class CalculateArticleSalesVolume extends Command
{
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
        $articleController = new ArticleController();
        $articleController->calculateSalesVolume();
    }
}
