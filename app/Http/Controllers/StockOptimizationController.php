<?php

namespace App\Http\Controllers;

use App\Jobs\OptimizeStock;
use Illuminate\Http\Request;

class StockOptimizationController extends Controller
{
    public function optimizeStock()
    {
        OptimizeStock::dispatch()->onQueue('main');

        return ApiResponseController::success();
    }
}
