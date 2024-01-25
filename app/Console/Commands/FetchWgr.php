<?php

namespace App\Console\Commands;

use App\Http\Controllers\WgrController;
use Illuminate\Console\Command;

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
    }
}
