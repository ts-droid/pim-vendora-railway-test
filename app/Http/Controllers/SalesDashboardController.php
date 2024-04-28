<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Reports\SalesDashboardReporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesDashboardController extends Controller
{
    public function index(Request $request)
    {
        $salesPersonIDs = $request->input('sales_person_id');
        $salesPersonIDs = explode(',', $salesPersonIDs);
        $salesPersonIDs = array_filter($salesPersonIDs);

        $customerNumber = (string) $request->input('customer_number');
        $supplierNumber = (string) $request->input('supplier_number');

        $addShipping = (bool) $request->input('add_shipping', '0');

        $period = [
            $request->input('period_from', date('Y-m-01')),
            $request->input('period_to', date('Y-m-d'))
        ];

        $reporter = new SalesDashboardReporter($salesPersonIDs, $customerNumber, $supplierNumber, $period, $addShipping);

        $topCustomers = $reporter->getTopCustomers();
        $countryChart = $reporter->getCountryChart($topCustomers);

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

    public function eol(Request $request)
    {
        $customerNumber = (string) $request->input('customer_number');

        $startDate = date('Y-01-01');
        $endDate = date('Y-m-d');

        // Fetch the customer ID
        $customerID = Customer::where('customer_number', $customerNumber)
            ->pluck('external_id')
            ->first();

        $articles = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->join('articles', 'articles.article_number', '=', 'sales_order_lines.article_number')
            ->select('articles.article_number', 'articles.description', 'articles.stock')
            ->where('status', '!=', 'Active')
            ->where('sales_orders.customer', '=', $customerID)
            ->whereBetween('sales_orders.date', [$startDate, $endDate])
            ->get();

        return ApiResponseController::success($articles->toArray());
    }

    public function suggestions(Request $request)
    {
        $customerNumber = (string) $request->input('customer_number');
        $sorting = (string) $request->input('sorting', 'bestseller');

        $startDate = date('Y-01-01');
        $endDate = date('Y-m-d');

        // Fetch the customer ID
        $customerID = Customer::where('customer_number', $customerNumber)
            ->pluck('external_id')
            ->first();

        // Fetch all purchased articles and suppliers
        $articles = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->join('articles', 'articles.article_number', '=', 'sales_order_lines.article_number')
            ->select('articles.article_number', 'articles.supplier_number')
            ->where('sales_orders.customer', '=', $customerID)
            ->whereBetween('sales_orders.date', [$startDate, $endDate])
            ->get();

        $suppliers = [];
        $articleNumbers = [];

        foreach($articles as $article) {
            if (in_array($article->article_number, $articleNumbers)) {
                continue;
            }

            $articleNumbers[] = $article->article_number;

            if (!isset($suppliers[$article->supplier_number])) {
                $supplier = Supplier::where('number', $article->supplier_number)
                    ->first()
                    ->toArray();

                $suppliers[$article->supplier_number] = [
                    'supplier' => $supplier,
                    'article_numbers' => []
                ];
            }

            $suppliers[$article->supplier_number]['article_numbers'][] = $article->article_number;
        }

        // Fetch suggestions for each supplier
        foreach ($suppliers as &$supplier) {
            $query = DB::table('articles')
                ->select('article_number', 'description')
                ->where('supplier_number', $supplier['supplier']['number'])
                ->whereNotIn('article_number', $supplier['article_numbers']);

            switch ($sorting) {
                case 'latest':
                    $query->orderBy('created_at', 'DESC');
                    break;

                case 'bestseller':
                default:
                    $query->orderBy('sales_60_days', 'DESC');
                    break;
            }

            $supplier['suggestions'] = $query->limit(5)
                ->get()
                ->toArray();
        }

        return ApiResponseController::success(array_values($suppliers));
    }
}
