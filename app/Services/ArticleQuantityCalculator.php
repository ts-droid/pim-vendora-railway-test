<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ArticleQuantityCalculator
{
    /**
     * Returns the number of incoming articles
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getIncoming(string $articleNumber): int
    {
        $incomingQuantities = self::getIncomingQuantities();

        return $incomingQuantities[$articleNumber] ?? 0;
    }

    public static function getIncomingQuantities(): array
    {
        // Try to get the results from the cache
        $incomingQuantities = Cache::get('incoming_quantities');

        // If the results are not in the cache
        if ($incomingQuantities === null) {
            // Fetch the results from the database
            $incomingQuantities = DB::table('purchase_order_lines')
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
                ->where('purchase_orders.status', '=', 'Open')
                ->where('purchase_order_lines.is_completed', '=' ,0)
                ->where('purchase_order_lines.is_canceled', '=' ,0)
                ->select('purchase_order_lines.article_number', DB::raw('SUM(quantity) as total_quantity'))
                ->groupBy('purchase_order_lines.article_number')
                ->get()
                ->keyBy('article_number')
                ->map(function ($row) {
                    return $row->total_quantity;
                })
                ->toArray();

            // Store the results in the cache for 10 minutes
            Cache::put('incoming_quantities', $incomingQuantities, 10);
        }

        return $incomingQuantities;
    }

    /**
     * Returns the number of items on active sales orders
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getOnOrder(string $articleNumber): int
    {
        $onOrderQuantities = self::getOnOrderQuantities();

        return $onOrderQuantities[$articleNumber] ?? 0;
    }

    public static function getOnOrderQuantities(): array
    {
        // Try to get the results from the cache
        $onOrderQuantities = Cache::get('on_order_quantities');

        // If the results are not in the cache
        if ($onOrderQuantities === null) {
            // Fetch the results from the database
            $onOrderQuantities = DB::table('sales_order_lines')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->where('sales_order_lines.is_completed', '=', 0)
                ->whereIn('sales_orders.status', ['Open', 'BackOrder', 'Hold'])
                ->select('sales_order_lines.article_number', DB::raw('SUM(quantity_open) as total_quantity'))
                ->groupBy('sales_order_lines.article_number')
                ->get()
                ->keyBy('article_number')
                ->map(function ($row) {
                    return $row->total_quantity;
                })
                ->toArray();

            // Store the results in the cache for 10 minutes
            Cache::put('on_order_quantities', $onOrderQuantities, 10);
        }

        return $onOrderQuantities;
    }

    /**
     * Returns the current net stock (stock + incoming - onOrder)
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getNetStock(string $articleNumber): int
    {
        $stock = Article::where('article_number', $articleNumber)->pluck('stock_on_hand')->first();
        $incoming = self::getIncoming($articleNumber);
        $onOrder = self::getOnOrder($articleNumber);

        return $stock + $incoming - $onOrder;
    }

    /**
     * Returns the sales per month based on provided period
     *
     * @param string $articleNumber
     * @param int $months
     * @return int
     */
    public static function getSalesPerMonth(string $articleNumber, int $months = 6): int
    {
        $salesPerMonthQuantities = self::getSalesPerMonthQuantities($months);

        return $salesPerMonthQuantities[$articleNumber] ?? 0;
    }

    public static function getSalesPerMonthQuantities(int $months = 6): array
    {
        // Try to get the results from the cache
        $salesPerMonthQuantities = Cache::get('sales_per_month_quantities_' . $months);

        // If the results are not in the cache
        if ($salesPerMonthQuantities === null) {
            $days = $months * 30;

            // Fetch the results from the database
            $salesPerMonthQuantities = DB::table('sales_order_lines')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->where('sales_orders.date', '>=', date('Y-m-d', strtotime('-' . $days . ' days')))
                ->select('sales_order_lines.article_number', DB::raw('SUM(quantity) as total_quantity'))
                ->groupBy('sales_order_lines.article_number')
                ->get()
                ->keyBy('article_number')
                ->map(function ($row) use ($months) {
                    return $row->total_quantity / $months;
                })
                ->toArray();

            // Store the results in the cache for 10 minutes
            Cache::put('sales_per_month_quantities_' . $months, $salesPerMonthQuantities, 10);
        }

        return $salesPerMonthQuantities;
    }

    /**
     * Return the current stock time (sales per month / netStock)
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getStockTime(string $articleNumber): int
    {
        $salesPerMonth = self::getSalesPerMonth($articleNumber);
        $netStock = self::getNetStock($articleNumber);

        if (!$salesPerMonth) {
            return 0;
        }

        return round($netStock / $salesPerMonth, 1);
    }
}
