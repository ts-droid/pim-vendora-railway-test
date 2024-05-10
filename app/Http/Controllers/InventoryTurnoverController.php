<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTurnoverController extends Controller
{
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

        if ($summary['avg_rate']) {
            $summary['stock_time'] = $summary['stock'] / $summary['avg_rate'];
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
