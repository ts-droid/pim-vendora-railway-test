<?php

namespace App\Services\Pricing;

use App\Models\Article;
use App\Services\EcbService;

/**
 * Margin-based price calculator.
 *
 * This service computes a full price picture for an article given a set of
 * slider-style inputs (RRP, our margin, reseller margin). It does NOT modify
 * the existing percent-markup pricing logic in ArticlePriceService — it's a
 * parallel tool meant for the admin "Priskalkylator" UI.
 *
 * Math:
 *   basePriceEx   = rrpEx * (1 − resellerMargin/100)
 *   ourMargin     = (basePriceEx − cost) / basePriceEx  × 100
 *   rrpFromMargin = cost / ((1 − ourMargin/100) * (1 − resellerMargin/100))
 */
class PriceCalculatorService
{
    private const VAT_RATE = 0.25; // 25% — Sweden standard
    private const SUPPORTED_CURRENCIES = ['SEK', 'EUR', 'NOK', 'DKK'];

    public function __construct(
        private readonly EcbService $ecb = new EcbService(),
    ) {
    }

    /**
     * Get the initial calculator data for an article.
     *
     * @return array Calculator state including cost, margins, RRP per currency.
     */
    public function initialState(Article $article): array
    {
        $cost = CostResolver::resolve($article);
        $margins = MarginResolver::resolve($article);
        $rrpExSEK = (float) ($article->rek_price_SEK ?? 0);

        if ($rrpExSEK <= 0 && $cost > 0) {
            // Seed a reasonable RRP if none stored: cost / (1 − m) / (1 − r)
            $rrpExSEK = $cost / max(0.01, 1 - $margins['minimum_margin'] / 100)
                             / max(0.01, 1 - $margins['reseller_margin'] / 100);
        }

        return $this->calculate($article, rrpExSEK: $rrpExSEK, resellerMargin: $margins['reseller_margin']);
    }

    /**
     * Recalculate based on slider input. Exactly one of rrpExSEK, ourMargin, or
     * resellerMargin can be adjusted at a time — the caller indicates which via
     * $source ('rrp', 'margin', 'reseller'), and the other values are derived.
     *
     * @param Article     $article
     * @param string|null $source          'rrp' | 'margin' | 'reseller' (what slider was moved)
     * @param float|null  $rrpExSEK        Current RRP ex VAT in SEK
     * @param float|null  $ourMargin       Desired our-margin %
     * @param float|null  $resellerMargin  Desired ÅF margin %
     */
    public function calculate(
        Article $article,
        ?string $source = null,
        ?float $rrpExSEK = null,
        ?float $ourMargin = null,
        ?float $resellerMargin = null,
    ): array {
        $cost = CostResolver::resolve($article);
        $margins = MarginResolver::resolve($article);

        $effResellerMargin = $resellerMargin ?? $margins['reseller_margin'];
        $effOurMargin = $ourMargin;
        $effRrpEx = $rrpExSEK ?? 0;

        // Derive missing value from the two others based on which slider moved
        if ($source === 'margin' && $effOurMargin !== null) {
            // Keep reseller margin, recalculate RRP
            if ($cost > 0 && $effOurMargin < 100 && $effResellerMargin < 100) {
                $basePriceEx = $cost / max(0.01, 1 - $effOurMargin / 100);
                $effRrpEx = $basePriceEx / max(0.01, 1 - $effResellerMargin / 100);
            }
        } elseif ($source === 'reseller' && $resellerMargin !== null) {
            // Keep our margin (if known), recalculate RRP
            if ($effOurMargin !== null && $cost > 0 && $effOurMargin < 100 && $effResellerMargin < 100) {
                $basePriceEx = $cost / max(0.01, 1 - $effOurMargin / 100);
                $effRrpEx = $basePriceEx / max(0.01, 1 - $effResellerMargin / 100);
            }
        }
        // source === 'rrp' or null: leave $effRrpEx as given

        $rrpIncSEK = $effRrpEx * (1 + self::VAT_RATE);
        $basePriceEx = $effRrpEx * max(0.0, 1 - $effResellerMargin / 100);
        $brutto = $basePriceEx - $cost;
        $distMarginPct = $basePriceEx > 0 ? (($basePriceEx - $cost) / $basePriceEx) * 100 : 0.0;
        $belowMinMargin = $distMarginPct < $margins['minimum_margin'];

        return [
            'cost' => round($cost, 2),
            'min_margin' => $margins['minimum_margin'],
            'standard_reseller_margin' => $margins['reseller_margin'],
            'margin_source' => $margins['source'],
            'rrp_ex_sek' => round($effRrpEx, 2),
            'rrp_inc_sek' => round($rrpIncSEK, 0),
            'final_price_ex' => round($basePriceEx, 2),
            'our_margin' => round($distMarginPct, 2),
            'reseller_margin' => round($effResellerMargin, 2),
            'brutto' => round($brutto, 2),
            'below_min_margin' => $belowMinMargin,
            'currencies' => $this->buildCurrencyGrid($rrpIncSEK),
            'rates_live' => true, // EcbService caches 10h — assume live unless it throws
        ];
    }

    /**
     * Build the per-currency RRP (incl. VAT) grid with smart rounding.
     *
     * @return array<string, array{rrp_inc_raw: float, rrp_inc_rounded: float, rrp_ex_rounded: float}>
     */
    private function buildCurrencyGrid(float $rrpIncSEK): array
    {
        $grid = [];
        foreach (self::SUPPORTED_CURRENCIES as $cur) {
            if ($cur === 'SEK') {
                $raw = $rrpIncSEK;
            } else {
                try {
                    $raw = $this->ecb->convertCurrency($rrpIncSEK, 'SEK', $cur);
                } catch (\Throwable $e) {
                    // If ECB is unreachable, skip this currency rather than fail the whole call
                    continue;
                }
            }

            $rounded = SmartRounder::round($cur, $raw);
            $grid[$cur] = [
                'rrp_inc_raw' => round($raw, 2),
                'rrp_inc_rounded' => (float) SmartRounder::format($cur, $rounded),
                'rrp_ex_rounded' => round($rounded / (1 + self::VAT_RATE), 2),
            ];
        }
        return $grid;
    }
}
