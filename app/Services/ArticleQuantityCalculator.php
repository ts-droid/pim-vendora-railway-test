<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\DB;

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
        return 0;

        return (int) DB::table('purchase_order_lines')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.status', '=', 'Open')
            ->where('purchase_order_lines.article_number', $articleNumber)
            ->sum('quantity');
    }

    /**
     * Returns the number of items on active sales orders
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getOnOrder(string $articleNumber): int
    {
        return 0;

        $quantity = (int) DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->where('sales_orders.status', '!=', 'Closed')
            ->where('sales_order_lines.article_number', $articleNumber)
            ->sum('quantity');

        return $quantity;
    }

    /**
     * Returns the current net stock (stock + incoming - onOrder)
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getNetStock(string $articleNumber): int
    {
        return 0;

        $stock = Article::where('article_number', $articleNumber)->pluck('stock')->first();
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
        return 0;

        $days = $months * 30;

        $sales = (int) DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->where('sales_order_lines.article_number', $articleNumber)
            ->where('sales_order_lines.created_at', '>=', date('Y-m-d', strtotime('-' . $days . ' days')))
            ->sum('quantity');

        return round($sales / $months);
    }

    /**
     * Return the current stock time (sales per month / netStock)
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getStockTime(string $articleNumber): int
    {
        return 0;

        $salesPerMonth = self::getSalesPerMonth($articleNumber);
        $netStock = self::getNetStock($articleNumber);

        if (!$netStock) {
            return 0;
        }

        return round($salesPerMonth / $netStock, 1);
    }
}
