<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\WMS\StockItemService;
use Illuminate\Console\Command;

class UpdateStockMovements extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warehouse:update-stock-movements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all stock movements.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting stock movements update.', $this->commandLogContext());

        $stockItemService = new StockItemService();
        $stockItemService->updateAllStockMovements();

        action_log('Finished stock movements update.', $this->commandLogContext());
    }
}
