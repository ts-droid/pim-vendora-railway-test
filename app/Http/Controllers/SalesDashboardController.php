<?php

namespace App\Http\Controllers;

use App\Services\Reports\SalesDashboardReporter;
use Illuminate\Http\Request;

class SalesDashboardController extends Controller
{
    public function index(Request $request)
    {
        $reporter = new SalesDashboardReporter();

        return response()->json([
            'summary' => $reporter->getSummary(),
            'topBrands' => $reporter->getTopBrands(),
            'topCustomers' => $reporter->getTopCustomers(),
            'orderPipeline' => $reporter->getOrderPipeline(),
        ]);
    }
}
