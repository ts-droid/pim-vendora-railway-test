<?php

namespace App\Console\Commands;

use App\Services\ArticleEolHandler;
use Illuminate\Console\Command;

class ProcessEolArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:process-eol';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes all EOL articles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->line('Deactivating EOL articles...');

        $eolHandler = new ArticleEolHandler();
        $eolHandler->inactivateArticles();

        $this->info('Done!');
    }
}
