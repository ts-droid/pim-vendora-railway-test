<?php

namespace App\Services\Pricing;

use App\Models\Article;
use App\Models\BundleComponent;

/**
 * Resolves the effective cost price for an article in SEK.
 *
 * Precedence:
 *   1. For bundles (article_type = 'Bundle'): sum of component
 *      cost_price_avg × quantity.
 *   2. For standard articles: cost_price_avg (from inventory receipts),
 *      fallback to external_cost (supplier price).
 *
 * This matches the precedence already used in InventoryTurnoverController.
 */
class CostResolver
{
    public static function resolve(Article $article): float
    {
        if ($article->article_type === 'Bundle') {
            return self::resolveBundleCost($article);
        }

        $cost = (float) ($article->cost_price_avg ?? 0);
        if ($cost > 0) {
            return $cost;
        }

        return (float) ($article->external_cost ?? 0);
    }

    private static function resolveBundleCost(Article $bundle): float
    {
        $components = BundleComponent::where('bundle_article_number', $bundle->article_number)
            ->orderBy('sort_order')
            ->get();

        if ($components->isEmpty()) {
            return 0.0;
        }

        $articleNumbers = $components->pluck('component_article_number')->unique();
        $articles = Article::whereIn('article_number', $articleNumbers)
            ->get()
            ->keyBy('article_number');

        $total = 0.0;
        foreach ($components as $comp) {
            $componentArticle = $articles->get($comp->component_article_number);
            if (!$componentArticle) {
                continue;
            }
            // Recursive resolve so nested bundles work
            $componentCost = self::resolve($componentArticle);
            $total += $componentCost * $comp->quantity;
        }

        return $total;
    }
}
