<?php

namespace App\Services\Pricing;

use App\Models\Article;
use App\Models\BundleComponent;
use App\Models\CurrencyRate;
use App\Models\SupplierArticlePrice;

/**
 * Resolves the effective cost price for an article in SEK.
 *
 * Precedence:
 *   1. For bundles (article_type = 'Bundle'): sum of component
 *      resolved cost × quantity (recursive).
 *   2. For standard articles:
 *      a. cost_price_avg (inventory receipts, already SEK) — if > 0
 *      b. supplier_article_prices.price converted from supplier's
 *         currency to SEK via currency_rates — if row exists and we
 *         have an FX rate
 *      c. external_cost (legacy SEK-converted fallback) — if > 0
 *
 * Kept structurally compatible with earlier resolve() callers.
 */
class CostResolver
{
    public static function resolve(Article $article): float
    {
        return self::resolveBreakdown($article)['sek'];
    }

    /**
     * Same as resolve() but returns source metadata (useful for UI).
     *
     * @return array{sek: float, source: string, raw_amount: float, raw_currency: string}
     */
    public static function resolveBreakdown(Article $article): array
    {
        if ($article->article_type === 'Bundle') {
            return [
                'sek' => self::resolveBundleCost($article),
                'source' => 'bundle_components',
                'raw_amount' => 0.0,
                'raw_currency' => 'SEK',
            ];
        }

        $costPriceAvg = (float) ($article->cost_price_avg ?? 0);
        if ($costPriceAvg > 0) {
            return [
                'sek' => $costPriceAvg,
                'source' => 'cost_price_avg',
                'raw_amount' => $costPriceAvg,
                'raw_currency' => 'SEK',
            ];
        }

        // No average cost — fall back to the latest supplier purchase
        // price and convert to SEK from the supplier's currency.
        $sap = SupplierArticlePrice::where('article_number', $article->article_number)
            ->orderByDesc('updated_at')
            ->first();
        if ($sap && (float) $sap->price > 0) {
            $converted = self::convertToSek((float) $sap->price, (string) $sap->currency);
            if ($converted > 0) {
                return [
                    'sek' => $converted,
                    'source' => 'supplier_article_prices',
                    'raw_amount' => (float) $sap->price,
                    'raw_currency' => (string) $sap->currency,
                ];
            }
        }

        $external = (float) ($article->external_cost ?? 0);
        return [
            'sek' => $external,
            'source' => $external > 0 ? 'external_cost' : 'none',
            'raw_amount' => $external,
            'raw_currency' => 'SEK',
        ];
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
            // Recursive resolve so nested bundles work + supplier-price
            // fallback applies to each component individually.
            $total += self::resolve($componentArticle) * (int) $comp->quantity;
        }

        return $total;
    }

    /**
     * Convert an amount in `$fromCurrency` to SEK using the latest
     * currency_rates row (`from_currency = $fromCurrency`, `to_currency = SEK`).
     * Returns 0.0 when no rate is available — caller decides how to
     * surface that to the user.
     */
    private static function convertToSek(float $amount, string $fromCurrency): float
    {
        $fromCurrency = strtoupper($fromCurrency);
        if ($fromCurrency === 'SEK' || $fromCurrency === '') {
            return $amount;
        }

        $rate = CurrencyRate::where('from_currency', $fromCurrency)
            ->where('to_currency', 'SEK')
            ->orderByDesc('date')
            ->first();
        if (!$rate) {
            return 0.0;
        }

        $rateValue = (float) $rate->rate;
        if ($rateValue <= 0) {
            return 0.0;
        }

        return $rate->mult_div === 'Multiply'
            ? $amount * $rateValue
            : $amount / $rateValue;
    }
}
