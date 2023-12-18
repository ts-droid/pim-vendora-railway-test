<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\DB;

class BestsellerCalculator
{
    public function calculateBestsellers()
    {
        $statsPeriod = 30;

        $startDate = date('Y-m-d', strtotime('-' . $statsPeriod . ' days'));
        $endDate = date('Y-m-d');

        $quantities = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->whereBetween('sales_orders.date', [$startDate, $endDate])
            ->groupBy('sales_order_lines.article_number')
            ->select('sales_order_lines.article_number', DB::raw('SUM(sales_order_lines.quantity) as total_quantity'))
            ->get()
            ->pluck('total_quantity', 'article_number')
            ->all();

        $stats = collect();

        foreach ($quantities as $articleNumber => $quantity) {
            $stats->push([
                'article_number' => $articleNumber,
                'quantity' => $quantity,
            ]);
        }

        // Sort by quantity
        $stats = $stats->sortByDesc('quantity');

        // Update the position for each article
        Article::update(['bestseller_position' => 0]);

        for ($i = 1;$i <= $stats->count();$i++) {
            $item = $stats[$i - 1];

            Article::where('article_number', $item['article_number'])
                ->update(['bestseller_position' => $i]);
        }
    }
}
