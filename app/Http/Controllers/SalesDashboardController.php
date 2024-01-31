<?php

namespace App\Http\Controllers;

use App\Services\Reports\SalesDashboardReporter;
use Illuminate\Http\Request;

class SalesDashboardController extends Controller
{
    public function index(Request $request)
    {
        $salesPersonID = $request->input('sales_person_id');
        $customerNumber = (string) $request->input('customer_number');

        if ($salesPersonID != '*') {
            $salesPersonID = (int) $salesPersonID;
        }

        $period = [
            $request->input('period_from', date('Y-m-01')),
            $request->input('period_to', date('Y-m-d'))
        ];

        $reporter = new SalesDashboardReporter($salesPersonID, $customerNumber, $period);

        return ApiResponseController::success([
            'summary' => $reporter->getSummary(),
            'topBrands' => $reporter->getTopBrands(),
            'topCustomers' => $reporter->getTopCustomers(),
            'topArticles' => $reporter->getTopArticles(),
            'orderPipeline' => $reporter->getOrderPipeline(),
            'charts' => $reporter->getCharts(),
            'period' => $period,
        ]);
    }
}
