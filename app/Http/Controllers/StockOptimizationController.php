<?php

namespace App\Http\Controllers;

use App\Jobs\OptimizeStock;
use Illuminate\Http\Request;

class StockOptimizationController extends Controller
{
    public function optimizeStock()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        return ApiResponseController::error('Disabled');

        OptimizeStock::dispatch()->onQueue('main');

        return ApiResponseController::success();
    }
}
