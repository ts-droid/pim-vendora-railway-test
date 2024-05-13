<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTurnoverController extends Controller
{
    public function brands(Request $request)
    {
        $period = (int) $request->input('period', 3);

        $lastPeriodStartDate = date('Y-m-d', strtotime('-' . ($period * 2) . ' months'));
        $startDate = date('Y-m-d', strtotime('-' . $period . ' months'));
        $endDate = date('Y-m-d');

        // Fetch total stock value
        $totalStockValue = DB::table('articles')
            ->sum(DB::raw('stock * IF(cost_price_avg > 0, cost_price_avg, external_cost)'));

        // Fetch all suppliers
        $suppliers = Supplier::select('id', 'number', 'brand_name')
            ->where('brand_name', '!=', '')
            ->get();

        // Fetch all articles
        $articles = DB::table('articles')
            ->select('id', 'article_number', 'description', 'stock', 'cost_price_avg', 'external_cost', 'webshop_created_at', 'supplier_number')
            ->get()
            ->groupBy('supplier_number');

        // Fetch all order lines within the period
        $orderLines = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_order_lines.sales_order_id', '=', 'sales_orders.id')
            ->join('articles', 'sales_order_lines.article_number', '=', 'articles.article_number')
            ->select('sales_order_lines.article_number', 'sales_order_lines.quantity', 'sales_orders.date', 'articles.supplier_number')
            ->whereBetween('sales_orders.date', [$lastPeriodStartDate, $endDate])
            ->get()
            ->groupBy('article_number');

        $summary = [
            'stock_value' => 0,
            'stock' => 0,
            'avg_rate' => 0,
            'avg_rate_last_period' => 0,
            'avg_rate_with_value' => 0,
            'avg_rate_with_value_last_period' => 0,
            'percent_of_total' => 0,
            'stock_time' => 0,
            'non_neg_summary' => [
                'stock' => 0,
                'sold_units' => 0,
            ],
        ];

        // Generate summary per supplier
        $supplierSummaries = [];

        foreach ($suppliers as $supplier) {
            $supplierSummaries[$supplier->number] = [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->brand_name,
                'stock_value' => 0,
                'stock' => 0,
                'avg_rate' => 0,
                'avg_rate_last_period' => 0,
                'avg_rate_with_value' => 0,
                'avg_rate_with_value_last_period' => 0,
                'percent_of_total' => 0,
                'stock_time' => 0,
                'non_neg_summary' => [
                    'stock' => 0,
                    'sold_units' => 0,
                ],
            ];

            $supplierArticles = $articles[$supplier->number] ?? [];

            $turnoverRates = [];
            $turnoverRatesLastPeriod = [];

            $turnoverRatesWithValue = [];
            $turnoverRatesLastPeriodWithValue = [];

            foreach ($supplierArticles as &$article) {
                $article->cost_price = $article->cost_price_avg ?: $article->external_cost;

                $article->stock_value = $article->stock * $article->cost_price;

                $article->stock_turnover_rate = 0;
                $article->stock_turnover_rate_last_period = 0;

                if (isset($orderLines[$article->article_number])) {
                    foreach ($orderLines[$article->article_number] as $orderLine) {
                        if ($orderLine->date >= $startDate) {
                            $article->stock_turnover_rate += $orderLine->quantity;

                            if ($article->stock >= 0) {
                                $supplierSummaries[$supplier->number]['non_neg_summary']['sold_units'] += $orderLine->quantity;
                            }
                        }

                        if ($orderLine->date >= $lastPeriodStartDate && $orderLine->date < $startDate) {
                            $article->stock_turnover_rate_last_period += $orderLine->quantity;
                        }
                    }
                }

                $article->stock_turnover_rate = intval($article->stock_turnover_rate / $period);
                $article->stock_turnover_rate_last_period = intval($article->stock_turnover_rate_last_period / $period);

                $article->stock_time = 0;
                if ($article->stock_turnover_rate) {
                    $article->stock_time = round($article->stock / $article->stock_turnover_rate, 1);
                }

                $turnoverRates[] = $article->stock_turnover_rate;
                $turnoverRatesLastPeriod[] = $article->stock_turnover_rate_last_period;

                if ($article->stock_value) {
                    $turnoverRatesWithValue[] = $article->stock_turnover_rate;
                    $turnoverRatesLastPeriodWithValue[] = $article->stock_turnover_rate_last_period;
                }

                $supplierSummaries[$supplier->number]['stock_value'] += $article->stock_value;
                $supplierSummaries[$supplier->number]['stock'] += $article->stock;

                if ($article->stock >= 0) {
                    $supplierSummaries[$supplier->number]['non_neg_summary']['stock'] += $article->stock;
                }

                $summary['stock_value'] += $article->stock_value;
                $summary['stock'] += $article->stock;
            }

            if (count($turnoverRates)) {
                $supplierSummaries[$supplier->number]['avg_rate'] = intval(array_sum($turnoverRates) / count($turnoverRates));
            }
            if (count($turnoverRatesLastPeriod)) {
                $supplierSummaries[$supplier->number]['avg_rate_last_period'] = intval(array_sum($turnoverRatesLastPeriod) / count($turnoverRatesLastPeriod));
            }

            if (count($turnoverRatesWithValue)) {
                $supplierSummaries[$supplier->number]['avg_rate_with_value'] = intval(array_sum($turnoverRatesWithValue) / count($turnoverRatesWithValue));
            }
            if (count($turnoverRatesLastPeriodWithValue)) {
                $supplierSummaries[$supplier->number]['avg_rate_with_value_last_period'] = intval(array_sum($turnoverRatesLastPeriodWithValue) / count($turnoverRatesLastPeriodWithValue));
            }

            if ($supplierSummaries[$supplier->number]['non_neg_summary']['sold_units']) {
                $supplierSummaries[$supplier->number]['stock_time'] = round($supplierSummaries[$supplier->number]['non_neg_summary']['stock'] / $supplierSummaries[$supplier->number]['non_neg_summary']['sold_units'], 1);
            }

            if ($totalStockValue) {
                $supplierSummaries[$supplier->number]['percent_of_total'] = round(($supplierSummaries[$supplier->number]['stock_value'] / $totalStockValue) * 100, 2);
            }
        }

        return ApiResponseController::success([
            'suppliers' => $supplierSummaries,
            'summary' => $summary,
        ]);
    }

    public function article(Request $request)
    {
        $period = (int) $request->input('period', 3);
        $articleNumber = $request->input('article_number');

        $lastPeriodStartDate = date('Y-m-d', strtotime('-' . ($period * 2) . ' months'));
        $startDate = date('Y-m-d', strtotime('-' . $period . ' months'));
        $endDate = date('Y-m-d');

        // Fetch all order lines within the period
        $orderLines = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_order_lines.sales_order_id', '=', 'sales_orders.id')
            ->select('article_number', 'quantity', 'sales_orders.date')
            ->where('sales_order_lines.article_number', $articleNumber)
            ->whereBetween('sales_orders.date', [$lastPeriodStartDate, $endDate])
            ->get()
            ->toArray();

        // Fetch the article
        $article = DB::table('articles')
            ->select('id', 'article_number', 'description', 'stock', 'cost_price_avg', 'external_cost', 'webshop_created_at')
            ->where('article_number', $articleNumber)
            ->first();

        if (!$article) {
            return ApiResponseController::error('Article not found');
        }

        $article->cost_price = $article->cost_price_avg ?: $article->external_cost;
        $article->stock_value = $article->stock * $article->cost_price;

        $article->stock_turnover_rate = 0;
        $article->stock_turnover_rate_last_period = 0;

        if ($orderLines && count($orderLines) > 0) {
            foreach ($orderLines as $orderLine) {
                if ($orderLine->date >= $startDate) {
                    $article->stock_turnover_rate += $orderLine->quantity;
                }

                if ($orderLine->date >= $lastPeriodStartDate && $orderLine->date < $startDate) {
                    $article->stock_turnover_rate_last_period += $orderLine->quantity;
                }
            }
        }

        $article->stock_turnover_rate = intval($article->stock_turnover_rate / $period);
        $article->stock_turnover_rate_last_period = intval($article->stock_turnover_rate_last_period / $period);

        $article->stock_time = 0;
        if ($article->stock_turnover_rate) {
            $article->stock_time = round($article->stock / $article->stock_turnover_rate, 1);
        }

        return ApiResponseController::success(['article' => (array) $article]);
    }

    public function index(Request $request)
    {
        $period = (int) $request->input('period', 3);
        $supplierID = (int) $request->integer('supplier', 0);

        $supplierNumber = null;
        $supplierName = null;

        if ($supplierID) {
            $supplier = DB::table('suppliers')
                ->select('number', 'brand_name')
                ->where('id', $supplierID)
                ->first();

            $supplierNumber = $supplier->number;
            $supplierName = $supplier->brand_name;
        }

        $includeEmptySuppliers = false;
        if ($supplierID == 242) {
            $includeEmptySuppliers = true;
        }

        $lastPeriodStartDate = date('Y-m-d', strtotime('-' . ($period * 2) . ' months'));
        $startDate = date('Y-m-d', strtotime('-' . $period . ' months'));
        $endDate = date('Y-m-d');

        $totalStockValue = 0;

        // Fetch all order lines within the period
        $orderLines = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_order_lines.sales_order_id', '=', 'sales_orders.id')
            ->select('article_number', 'quantity', 'sales_orders.date')
            ->whereBetween('sales_orders.date', [$lastPeriodStartDate, $endDate])
            ->get()
            ->groupBy('article_number')
            ->toArray();

        // Fetch all articles
        $articlesQuery = DB::table('articles')
            ->select('id', 'article_number', 'description', 'stock', 'cost_price_avg', 'external_cost', 'webshop_created_at');

        if ($supplierNumber) {
            if ($supplierName) {
                $articlesQuery->where(function($query) use ($supplierNumber, $supplierName) {
                    $query->orWhere('supplier_number', $supplierNumber)
                        ->orWhere('description', 'LIKE', '%' . $supplierName . '%');
                });
            }
            else {
                $articlesQuery->where('supplier_number', $supplierNumber);
            }

            if ($includeEmptySuppliers) {
                $articlesQuery->orWhere('supplier_number', '')
                    ->orWhereNull('supplier_number');
            }

            // Fetch total stock value to be able to calculate percentage of total
            $totalStockValue = DB::table('articles')
                ->sum(DB::raw('stock * IF(cost_price_avg > 0, cost_price_avg, external_cost)'));
        }

        $articles = $articlesQuery->get();

        $summary = [
            'stock_value' => 0,
            'stock' => 0,
            'avg_rate' => 0,
            'avg_rate_last_period' => 0,
            'avg_rate_with_value' => 0,
            'avg_rate_with_value_last_period' => 0,
            'percent_of_total' => 0,
            'stock_time' => 0,
            'non_neg_summary' => [
                'stock' => 0,
                'sold_units' => 0,
            ],
        ];

        $turnoverRates = [];
        $turnoverRatesLastPeriod = [];

        $turnoverRatesWithValue = [];
        $turnoverRatesLastPeriodWithValue = [];

        if ($articles) {
            foreach ($articles as &$article) {
                $article->cost_price = $article->cost_price_avg ?: $article->external_cost;
                $article->stock_value = $article->stock * $article->cost_price;

                $article->stock_turnover_rate = 0;
                $article->stock_turnover_rate_last_period = 0;

                if (isset($orderLines[$article->article_number])) {
                    foreach ($orderLines[$article->article_number] as $orderLine) {
                        if ($orderLine->date >= $startDate) {
                            $article->stock_turnover_rate += $orderLine->quantity;

                            if ($article->stock >= 0) {
                                $summary['non_neg_summary']['sold_units'] += $orderLine->quantity;
                            }
                        }

                        if ($orderLine->date >= $lastPeriodStartDate && $orderLine->date < $startDate) {
                            $article->stock_turnover_rate_last_period += $orderLine->quantity;
                        }
                    }
                }

                $article->stock_turnover_rate = intval($article->stock_turnover_rate / $period);
                $article->stock_turnover_rate_last_period = intval($article->stock_turnover_rate_last_period / $period);

                $article->stock_time = 0;
                if ($article->stock_turnover_rate) {
                    $article->stock_time = round($article->stock / $article->stock_turnover_rate, 1);
                }

                $turnoverRates[] = $article->stock_turnover_rate;
                $turnoverRatesLastPeriod[] = $article->stock_turnover_rate_last_period;

                if ($article->stock_value > 0) {
                    $turnoverRatesWithValue[] = $article->stock_turnover_rate;
                    $turnoverRatesLastPeriodWithValue[] = $article->stock_turnover_rate_last_period;
                }

                $summary['stock_value'] += $article->stock_value;
                $summary['stock'] += $article->stock;

                if ($article->stock >= 0) {
                    $summary['non_neg_summary']['stock'] += $article->stock;
                }
            }
        }

        if (count($turnoverRates)) {
            $summary['avg_rate'] = intval(array_sum($turnoverRates) / count($turnoverRates));
        }
        if (count($turnoverRatesLastPeriod)) {
            $summary['avg_rate_last_period'] = intval(array_sum($turnoverRatesLastPeriod) / count($turnoverRatesLastPeriod));
        }

        if (count($turnoverRatesWithValue)) {
            $summary['avg_rate_with_value'] = intval(array_sum($turnoverRatesWithValue) / count($turnoverRatesWithValue));
        }
        if (count($turnoverRatesLastPeriodWithValue)) {
            $summary['avg_rate_with_value_last_period'] = intval(array_sum($turnoverRatesLastPeriodWithValue) / count($turnoverRatesLastPeriodWithValue));
        }

        if ($summary['non_neg_summary']['sold_units']) {
            $summary['stock_time'] = $summary['non_neg_summary']['stock'] / $summary['non_neg_summary']['sold_units'];
        }

        if ($totalStockValue) {
            $summary['percent_of_total'] = round(($summary['stock_value'] / $totalStockValue) * 100, 2);
        }

        return ApiResponseController::success([
            'articles' => $articles->toArray(),
            'summary' => $summary,
        ]);
    }
}
