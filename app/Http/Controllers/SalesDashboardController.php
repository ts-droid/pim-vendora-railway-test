<?php

namespace App\Http\Controllers;

use App\Models\Article;
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
            'salesPersonsToplist' => $reporter->getSalesPersonsToplist(),
            'charts' => $reporter->getCharts(),
            'countryChart' => $countryChart,
            'period' => $period,
            'budget_fulfillment' => $reporter->getBudgetFulfillment(),
        ]);
    }

    public function summary(Request $request)
    {
        $period = [
            $request->input('start_date', date('Y-01-01')),
            $request->input('end_date', date('Y-m-d'))
        ];

        $reporter = new SalesDashboardReporter([], '', '', $period, false);

        return ApiResponseController::success($reporter->getSummary());
    }

    public function eol(Request $request)
    {
        $customerNumber = (string) $request->input('customer_number');
        $type = (string) $request->input('type');

        $startDate = date('Y-01-01');
        $endDate = date('Y-m-d');

        // Fetch the customer ID
        $customerID = Customer::where('customer_number', $customerNumber)
            ->pluck('external_id')
            ->first();

        $articlesQuery = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->join('articles', 'articles.article_number', '=', 'sales_order_lines.article_number')
            ->select('articles.article_number', 'articles.description', 'articles.stock')
            ->where('articles.status', '!=', 'Active')
            ->where('sales_orders.customer', '=', $customerID)
            ->whereBetween('sales_orders.date', [$startDate, $endDate]);

        switch ($type) {
            case 'no_stock':
                $articlesQuery->where('stock', '<=', 0);
                break;

            case 'has_stock':
                $articlesQuery->where('stock', '>', 0);
                break;
        }

        $articles = $articlesQuery->get();

        // Filter out duplicates of article_number
        $articles = $articles->unique('article_number');

        $articles = $articles->toArray();
        $articles = array_values($articles);

        return ApiResponseController::success($articles);
    }

    public function intel(Request $request)
    {
        $customerNumber = (string) $request->input('customer_number');
        $new = (bool) ($request->input('type') == 'new');

        $startDate = date('Y-01-01');
        $endDate = date('Y-m-d');

        $languageController = new LanguageController();
        $languages = $languageController->getAllLanguages();

        // Fetch the main customer
        $customer = Customer::where('customer_number', $customerNumber)->first();

        // Fetch completed articles
        $completedArticleIDs = Customer::where('customer_number', $customerNumber)
            ->pluck('intel_articles')
            ->first();

        $completedArticleIDs = explode(',', $completedArticleIDs);

        // Fetch customers with similar revenue
        $customersAbove = Customer::where('revenue', '>', $customer->revenue)
            ->orderBy('revenue', 'ASC')
            ->limit(4)
            ->get()
            ->toArray();

        $customersBelow = Customer::where('revenue', '<', $customer->revenue)
            ->orderBy('revenue', 'DESC')
            ->limit(4)
            ->get()
            ->toArray();

        $countAbove = count($customersAbove);
        $countBelow = count($customersBelow);

        // Initialize the number to take from each group
        $numFromAbove = min(2, $countAbove);
        $numFromBelow = 4 - $numFromAbove;

        // Check if there are enough below, if not adjust from above
        if ($numFromBelow > $countBelow) {
            $numFromBelow = $countBelow;
            $numFromAbove = 4 - $numFromBelow;
        }

        // Select the customers from above and below
        $selectedAbove = array_slice($customersAbove, 0, $numFromAbove);
        $selectedBelow = array_slice($customersBelow, 0, $numFromBelow);

        // Combined selected customers
        $similarCustomers = array_merge($selectedAbove, $selectedBelow);
        $similarCustomers = collect($similarCustomers);

        // Fetch all articles purchased by the main customer
        $articleNumbers = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->join('articles', 'articles.article_number', '=', 'sales_order_lines.article_number')
            ->select('articles.article_number')
            ->where('sales_orders.customer', '=', $customer->external_id)
            ->whereBetween('sales_orders.date', [$startDate, $endDate])
            ->get()
            ->pluck('article_number');

        // Fetch all articles purchased by the similar customers
        $columns = ['articles.article_number', 'articles.description', 'articles.sales_60_days', 'articles.supplier_number'];
        foreach ($languages as $language) {
            $columns[] = 'reseller_url_' . $language->language_code;
        }


        $articlesQuery = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->join('articles', 'articles.article_number', '=', 'sales_order_lines.article_number')
            ->select($columns)
            ->whereIn('sales_orders.customer', $similarCustomers->pluck('external_id'))
            ->whereBetween('sales_orders.date', [$startDate, $endDate]);

        if ($new) {
            $articlesQuery->whereNotIn('articles.article_number', $articleNumbers)
                ->whereNotIn('articles.id', $completedArticleIDs);
        }
        else {
            $articlesQuery->whereIn('articles.id', $completedArticleIDs);
        }

        $articles = $articlesQuery->get();

        // Group by supplier
        $articlesBySupplier = [];
        $articleNumbers = [];

        foreach($articles as $article) {
            if (in_array($article->article_number, $articleNumbers)) {
                continue;
            }

            $articleNumbers[] = $article->article_number;

            if (!isset($articlesBySupplier[$article->supplier_number])) {
                $supplier = Supplier::where('number', $article->supplier_number)->first();

                $articlesBySupplier[$article->supplier_number] = [
                    'supplier' => $supplier ? $supplier->toArray() : null,
                    'articles' => []
                ];
            }

            if (count($articlesBySupplier[$article->supplier_number]['articles']) >= 5) {
                continue;
            }

            $customerIDs = DB::table('sales_order_lines')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->select('sales_orders.customer')
                ->where('sales_order_lines.article_number', $article->article_number)
                ->whereBetween('sales_orders.date', [$startDate, $endDate])
                ->groupBy('sales_orders.customer')
                ->get()
                ->pluck('customer')
                ->toArray();

            $customerNames = [];

            foreach ($similarCustomers as $similarCustomer) {
                if (in_array($similarCustomer['external_id'], $customerIDs)) {
                    $customerNames[] = $similarCustomer['name'];
                }
            }

            $article->customers = $customerNames;

            $articlesBySupplier[$article->supplier_number]['articles'][] = $article;
        }

        $articlesBySupplier = array_values($articlesBySupplier);

        return ApiResponseController::success($articlesBySupplier);
    }

    public function intelComplete(Request $request)
    {
        $customerNumber = (string) $request->input('customer_number');
        $articleNumber = (string) $request->input('article_number');
        $isDone = (bool) $request->input('is_done', false);

        $customer = Customer::where('customer_number', $customerNumber)->first();
        $article = Article::where('article_number', $articleNumber)->first();

        if (!$customer || !$article) {
            return ApiResponseController::error('Customer or article not found');
        }

        $intelIDs = explode(',', $customer->intel_articles);
        if ($isDone) {
            $intelIDs[] = $article->id;
        }
        else {
            $intelIDs = array_diff($intelIDs, [$article->id]);
        }

        // Remove duplicates
        $intelIDs = array_unique($intelIDs);

        // Remove empty values
        $intelIDs = array_filter($intelIDs);

        $customer->intel_articles = implode(',', $intelIDs);
        $customer->save();

        return ApiResponseController::success();
    }

    public function suggestionsComplete(Request $request)
    {
        $customerNumber = (string) $request->input('customer_number');
        $articleNumber = (string) $request->input('article_number');
        $isDone = (bool) $request->input('is_done', false);

        $customer = Customer::where('customer_number', $customerNumber)->first();
        $article = Article::where('article_number', $articleNumber)->first();

        if (!$customer || !$article) {
            return ApiResponseController::error('Customer or article not found');
        }

        $suggestionIDs = explode(',', $customer->suggestions_articles);
        if ($isDone) {
            $suggestionIDs[] = $article->id;
        }
        else {
            $suggestionIDs = array_diff($suggestionIDs, [$article->id]);
        }

        // Remove duplicates
        $suggestionIDs = array_unique($suggestionIDs);

        // Remove empty values
        $suggestionIDs = array_filter($suggestionIDs);

        $customer->suggestions_articles = implode(',', $suggestionIDs);
        $customer->save();

        return ApiResponseController::success();
    }

    public function suggestions(Request $request)
    {
        $customerNumber = (string) $request->input('customer_number');
        $sorting = (string) $request->input('sorting', 'bestseller');
        $numProducts = (int) $request->input('num_products', 5);
        $new = (bool) ($request->input('type') == 'new');

        $startDate = date('Y-01-01');
        $endDate = date('Y-m-d');

        $languageController = new LanguageController();
        $languages = $languageController->getAllLanguages();

        // Fetch the customer ID
        $customerID = Customer::where('customer_number', $customerNumber)
            ->pluck('external_id')
            ->first();

        // Fetch suggested articles
        $suggestedArticleIDs = Customer::where('customer_number', $customerNumber)
            ->pluck('suggestions_articles')
            ->first();

        $suggestedArticleIDs = explode(',', $suggestedArticleIDs);

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
                    ->first();

                $suppliers[$article->supplier_number] = [
                    'supplier' => $supplier ? $supplier->toArray() : null,
                    'article_numbers' => []
                ];
            }

            $suppliers[$article->supplier_number]['article_numbers'][] = $article->article_number;
        }

        // Fetch suggestions for each supplier
        foreach ($suppliers as &$supplier) {
            if (!$supplier['supplier']) {
                $supplier['suggestions'] = [];
                continue;
            }

            $columns = ['article_number', 'description'];
            foreach ($languages as $language) {
                $columns[] = 'reseller_url_' . $language->language_code;
            }

            $query = DB::table('articles')
                ->select($columns)
                ->where('supplier_number', $supplier['supplier']['number']);

            if ($new) {
                $query->whereNotIn('article_number', $supplier['article_numbers'])
                    ->whereNotIn('id', $suggestedArticleIDs);
            }
            else {
                $query->whereIn('id', $suggestedArticleIDs);
            }

            switch ($sorting) {
                case 'latest':
                    $query->orderBy('created_at', 'DESC');
                    break;

                case 'bestseller':
                default:
                    $query->orderBy('sales_60_days', 'DESC');
                    break;
            }

            $supplier['suggestions'] = $query->limit($numProducts)
                ->get()
                ->toArray();
        }

        return ApiResponseController::success(array_values($suppliers));
    }
}
