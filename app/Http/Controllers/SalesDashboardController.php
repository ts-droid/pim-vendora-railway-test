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

        // Generate country chart
        $countryChart = [];
        $totalCountryAmount = 0;

        foreach ($topCustomers as $topCustomer) {
            if (!isset($countryChart[$topCustomer['country']])) {
                $countryChart[$topCustomer['country']] = 0;
            }
            $countryChart[$topCustomer['country']] += $topCustomer['amount'];
            $totalCountryAmount += $topCustomer['amount'];
        }

        // Transform country chart to percentage
        if ($totalCountryAmount > 0) {
            $newCountryChart = [];

            foreach ($countryChart as $country => $amount) {
                $countryChart[$country] = round($amount / $totalCountryAmount * 100, 2);

                $percentage = round($amount / $totalCountryAmount * 100, 2);
                $newCountryChart[$country . ' (' . $percentage . '%)'] = $percentage;
            }

            $countryChart = $newCountryChart;
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
