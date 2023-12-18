<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\DB;

class BestsellerCalculator
{
    public function calculateBestsellers()
    {
        $articles = Article::all();

        if (!$articles) {
            return;
        }

        $statsPeriod = 30;

        $startDate = date('Y-m-d', strtotime('-' . $statsPeriod . ' days'));
        $endDate = date('Y-m-d');

        $stats = collect();

        foreach ($articles as $article) {
            $quantity = DB::table('sales_order_lines')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->where('sales_order_lines.article_number', $article->article_number)
                ->whereBetween('sales_orders.date', [$startDate, $endDate])
                ->sum('sales_order_lines.quantity');

            $stats->push([
                'article' => $article,
                'quantity' => $quantity,
            ]);
        }

        // Sort by quantity
        $stats = $stats->sortByDesc('quantity');

        // Update the position for each article
        for ($i = 1;$i <= $stats->count();$i++) {
            $article = $stats[$i - 1]['article'];

            $article->update([
                'bestseller_position' => $i,
            ]);
        }
    }
}
