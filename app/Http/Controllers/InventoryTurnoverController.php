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

        $lastPeriodStartDate = date('Y-m-d', strtotime('-' . ($period * 2) . ' months'));
        $startDate = date('Y-m-d', strtotime('-' . $period . ' months'));
        $endDate = date('Y-m-d');

        // Fetch all order lines within the period
        $orderLines = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_order_lines.sales_order_id', '=', 'sales_orders.id')
            ->select('article_number', 'quantity', 'sales_orders.date')
            ->whereBetween('sales_orders.date', [$lastPeriodStartDate, $endDate])
            ->get()
            ->groupBy('article_number')
            ->toArray();

        // Fetch all articles
        $articles = DB::table('articles')
            ->select('id', 'article_number', 'description', 'stock', 'cost_price_avg', 'external_cost')
            ->get();

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
            }
        }

        return ApiResponseController::success([
            'articles' => $articles->toArray(),
        ]);
    }
}
