<?php

namespace App\Services;

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
    }
}
