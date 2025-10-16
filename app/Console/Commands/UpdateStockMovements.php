<?php

namespace App\Console\Commands;

use App\Services\WMS\StockItemService;
use Illuminate\Console\Command;

class UpdateStockMovements extends Command
{
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
        $stockItemService = new StockItemService();
        $stockItemService->updateAllStockMovements();
    }
}
