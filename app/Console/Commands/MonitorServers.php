<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\ServerMonitor;
use Illuminate\Console\Command;

class MonitorServers extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'servers:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor the up status of the servers.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting server monitor run.', $this->commandLogContext());

        $serverMonitor = new ServerMonitor();
        $serverMonitor->monitor();

        action_log('Finished server monitor run.', $this->commandLogContext());
    }
}
