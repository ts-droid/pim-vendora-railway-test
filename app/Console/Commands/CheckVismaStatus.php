<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Http\Controllers\StatusIndicatorController;
use App\Http\Controllers\VismaNetController;
use Illuminate\Console\Command;

class CheckVismaStatus extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Checking Visma.net integration status.', $this->commandLogContext());

        $vismaController = new VismaNetController();

        $isActive = $vismaController->isActive();

        if ($isActive) {
            StatusIndicatorController::ping('Visma.net integration', 300);

            $this->info('Visma.net integration is active.');
            action_log('Visma.net integration is active.', $this->commandLogContext([
                'is_active' => true,
            ]));
        } else {
            $this->error('Visma.net integration is not active.');
            action_log('Visma.net integration is not active.', $this->commandLogContext([
                'is_active' => false,
            ]), 'warning');
        }
    }
}
