<?php

namespace App\Http\Controllers;

use App\Services\Reports\SalesDashboardReporter;
use Illuminate\Http\Request;

class SalesDashboardController extends Controller
{
    public function index(Request $request)
    {
        $salesPersonIDs = $request->input('sales_person_id');
        $salesPersonIDs = explode(',', $salesPersonIDs);
        $salesPersonIDs = array_filter($salesPersonIDs);

        $customerNumber = (string) $request->input('customer_number');
        $supplierNumber = (string) $request->input('supplier_number');

        $period = [
            $request->input('period_from', date('Y-m-01')),
            $request->input('period_to', date('Y-m-d'))
        ];

        $reporter = new SalesDashboardReporter($salesPersonIDs, $customerNumber, $supplierNumber, $period);

        $topCustomers = $reporter->getTopCustomers();

        $countryChart = [];
        foreach ($topCustomers as $topCustomer) {
            if (!isset($countryChart[$topCustomer['country']])) {
                $countryChart[$topCustomer['country']] = 0;
            }
            $countryChart[$topCustomer['country']] += $topCustomer['amount'];
        }


        return ApiResponseController::success([
            'summary' => $reporter->getSummary(),
            'topBrands' => $reporter->getTopBrands(),
            'topCustomers' => $topCustomers,
            'topArticles' => $reporter->getTopArticles(),
            'orderPipeline' => $reporter->getOrderPipeline(),
            'charts' => $reporter->getCharts(),
            'countryChart' => $countryChart,
            'period' => $period,
        ]);
    }
}
