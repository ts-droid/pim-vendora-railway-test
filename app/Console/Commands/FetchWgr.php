<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Http\Controllers\WgrController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchWgr extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting WGR fetch.', $this->commandLogContext());

        if (!is_wgr_active()) {
            $this->info('WGR integration is not active');
            action_log('WGR integration inactive, skipping fetch.', $this->commandLogContext(), 'warning');
            return;
        }

        $type = $this->argument('type');
        $skipImages = (int) $this->argument('skipImages');
        $data = $this->argument('data');

        $forceAll = $type === 'all';

        $wgrController = new WgrController();

        if ($data == 'all') {
            $wgrController->fetchAll($forceAll, $skipImages);
        }
        else if ($data == 'categories') {
            $wgrController->fetchCategories();
        }
        else if ($data == 'files') {
            $wgrController->fetchFiles();
        }
        else if ($data == 'reviews') {
            $wgrController->fetchReviews();
        }
        else if ($data == 'prices') {
            $wgrController->fetchPriceLists();
        }
        else if ($data == 'orders') {
            $wgrController->fetchOrders();
        }

        action_log('Finished WGR fetch.', $this->commandLogContext([
            'type' => $type,
            'skip_images' => $skipImages,
            'data_group' => $data,
        ]));
    }
}
