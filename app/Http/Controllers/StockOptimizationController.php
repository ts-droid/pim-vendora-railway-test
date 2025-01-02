<?php

namespace App\Http\Controllers;

use App\Jobs\OptimizeStock;
use Illuminate\Http\Request;

class StockOptimizationController extends Controller
{
    public function optimizeStock()
    {
        ConfigController::setConfigs(['optimize_stock_running' => 1]);

        OptimizeStock::dispatch()->onQueue('main');

        return ApiResponseController::success();
    }
}
