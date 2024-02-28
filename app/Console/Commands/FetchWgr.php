<?php

namespace App\Console\Commands;

use App\Http\Controllers\WgrController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchWgr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wgr:fetch {type=updated} {skipImages=0} {data=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches data from the WGR API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lockName = 'wgr-fetch-command';
        $lockTimeout = 60 * 60 * 2; // 2 hours

        // attempt to acquire the lock
        if (Cache::lock($lockName, $lockTimeout)->get()) {

            try {

                $type = $this->argument('type');
                $skipImages = (int) $this->argument('skipImages');
                $data = $this->argument('data');

                $forceAll = $type === 'all';

                $wgrController = new WgrController();

                if ($data == 'all') {
                    $wgrController->fetchAll($forceAll, $skipImages);
                }
                else if ($data == 'prices') {
                    $wgrController->fetchPriceLists();
                }

            } finally {
                // Release the lock after the command is finished
                Cache::lock($lockName)->release();
            }

        }
        else {
            $this->info('Another instance of the command is already running.');
        }
    }
}
