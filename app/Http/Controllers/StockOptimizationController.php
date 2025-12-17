<?php

namespace App\Http\Controllers;

use App\Jobs\OptimizeStock;
use Illuminate\Http\Request;

class StockOptimizationController extends Controller
{
    public function optimizeStock()
    {
        return ApiResponseController::error('Disabled');

        OptimizeStock::dispatch()->onQueue('main');

        return ApiResponseController::success();
    }
}
