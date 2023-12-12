<?php

namespace App\Console\Commands;

use App\Services\ServerMonitor;
use Illuminate\Console\Command;

class MonitorServers extends Command
{
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
        $serverMonitor = new ServerMonitor();
        $serverMonitor->monitor();

    }
}
