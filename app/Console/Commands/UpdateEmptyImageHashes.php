<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Http\Controllers\ArticleController;
use Illuminate\Console\Command;

class UpdateEmptyImageHashes extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting update of empty article image hashes.', $this->commandLogContext());

        $articleController = new ArticleController();
        $articleController->updateEmptyImageHashes();

        $this->info('Done!');
        action_log('Finished update of empty article image hashes.', $this->commandLogContext());
    }
}
