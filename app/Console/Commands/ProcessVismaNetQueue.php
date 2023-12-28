<?php

namespace App\Console\Commands;

use App\Services\VismaNet\VismaNetQueueService;
use Illuminate\Console\Command;

class ProcessVismaNetQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visma:process-queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the Visma.net API queue.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->line('Processing Visma.net API queue...');

        $service = new VismaNetQueueService();
        $service->processQueue();

        $this->info('Done processing Visma.net API queue.');
    }
}
