<?php

namespace App\Http\Controllers;

use App\Services\Reports\SalesDashboardReporter;
use Illuminate\Http\Request;

class SalesDashboardController extends Controller
{
    public function index(Request $request)
    {
        $salesPersonID = (int) $request->input('sales_person_id');

        $reporter = new SalesDashboardReporter($salesPersonID);

        return ApiResponseController::success([
            'summary' => $reporter->getSummary(),
            'topBrands' => $reporter->getTopBrands(),
            'topCustomers' => $reporter->getTopCustomers(),
            'orderPipeline' => $reporter->getOrderPipeline(),
        ]);
    }
}
