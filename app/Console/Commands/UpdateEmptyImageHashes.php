<?php

namespace App\Console\Commands;

use App\Http\Controllers\ArticleController;
use Illuminate\Console\Command;

class UpdateEmptyImageHashes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'article-images:update-empty-hashes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the image hash on empty article images.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $articleController = new ArticleController();
        $articleController->updateEmptyImageHashes();

        $this->info('Done!');
    }
}
