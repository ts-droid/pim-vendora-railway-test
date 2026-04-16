<?php

namespace App\Services\Pricing;

/**
 * Psychological price rounding ported from the Vendora Priskalkylator app.
 *
 * Finds the nearest "sellable" price ending based on the currency and price
 * range. SEK/NOK/DKK use integer endings (.29, .49, .79, .99), while EUR/USD
 * use decimal endings (0.99, 2.99, 4.99, 7.99, 9.99).
 *
 * The rounder prefers the *next price at or above* the raw input so that
 * calculated margins are preserved or slightly improved, never reduced.
 */
class SmartRounder
{
    /**
     * @param string $currency  ISO currency code: SEK, EUR, NOK, DKK, USD
     * @param float  $raw       Raw computed price
     * @return float Rounded price
     */
    public static function round(string $currency, float $raw): float
    {
        if ($raw <= 0) {
            return $raw;
        }

        if (in_array($currency, ['SEK', 'NOK', 'DKK'], true)) {
            if ($raw < 500) {
                return self::findBestRound($raw, [29, 49, 79, 99], 100);
            }
            if ($raw < 1000) {
                return self::findBestRound($raw, [49, 99], 100);
            }
            if ($raw < 10000) {
                return self::findBestRound($raw, [99], 100);
            }
            return self::findBestRound($raw, [499, 999], 1000);
        }

        // EUR, USD, other decimal currencies
        if ($raw < 50) {
            return self::findBestRound($raw, [0.99, 2.99, 4.99, 7.99, 9.99], 10);
        }
        if ($raw < 100) {
            return self::findBestRound($raw, [4.99, 9.99], 10);
        }
        if ($raw < 1000) {
            return self::findBestRound($raw, [9.99], 10);
        }
        return self::findBestRound($raw, [49.99, 99.99], 100);
    }

    /**
     * Find the nearest psychological price at or above $raw. Falls back to the
     * closest candidate if no value at/above exists (very rare edge case).
     *
     * @param float    $raw
     * @param float[]  $endings
     * @param float    $step    Range step (100, 1000, 10, 100)
     */
    private static function findBestRound(float $raw, array $endings, float $step): float
    {
        $base = floor($raw / $step) * $step;
        $candidates = [];

        foreach ([-$step, 0, $step, $step * 2] as $offset) {
            foreach ($endings as $ending) {
                $value = $base + $offset + $ending;
                if ($value > 0) {
                    $candidates[] = $value;
                }
            }
        }

        $above = array_values(array_filter($candidates, fn ($c) => $c >= $raw));
        if (!empty($above)) {
            sort($above);
            return $above[0];
        }

        usort($candidates, fn ($a, $b) => abs($a - $raw) <=> abs($b - $raw));
        return $candidates[0] ?? $raw;
    }

    /**
     * Format a currency value for display. Integer for SEK/NOK/DKK, 2 decimals
     * for EUR/USD.
     */
    public static function format(string $currency, float $value): string
    {
        if (in_array($currency, ['EUR', 'USD'], true)) {
            return number_format($value, 2, '.', '');
        }
        return (string) (int) round($value);
    }
}
