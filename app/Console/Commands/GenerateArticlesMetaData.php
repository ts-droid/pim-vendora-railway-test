<?php

namespace App\Console\Commands;

use App\Http\Controllers\MetaDataController;
use Illuminate\Console\Command;

class GenerateArticlesMetaData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meta-data:generate-articles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate meta data for articles missing meta data.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $metaDataController = new MetaDataController();
        $metaDataController->processArticles();
    }
}
