<?php

namespace App\Console\Commands;

use App\Http\Controllers\StatusIndicatorController;
use App\Http\Controllers\VismaNetController;
use Illuminate\Console\Command;

class CheckVismaStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visma:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks if the Visma.net integration is active.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $vismaController = new VismaNetController();

        $isActive = $vismaController->isActive();

        if ($isActive) {
            StatusIndicatorController::ping('Visma.net integration', 300);

            $this->info('Visma.net integration is active.');
        } else {
            $this->error('Visma.net integration is not active.');
        }
    }
}
