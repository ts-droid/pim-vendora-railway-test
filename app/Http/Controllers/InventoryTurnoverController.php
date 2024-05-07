<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class InventoryTurnoverController extends Controller
{
    public function index(Request $request)
    {
        $period = (int) $request->input('period', 6);

        $articles = Article::select('id', 'article_number', 'description', 'stock', 'cost_price_avg', 'external_cost')
            ->get();

        if ($articles) {
            foreach ($articles as &$article) {
                $article->cost_price = $article->cost_price_avg ?: $article->external_cost;
                $article->stock_value = $article->stock * $article->cost_price;
            }
        }

        return ApiResponseController::success([
            'articles' => $articles->toArray(),
        ]);
    }
}
