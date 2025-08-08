<?php

namespace App\Services;

use App\Models\Article;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

class PurchasePlanner
{
    private const ZSCORE_LIMIT          = 3.0;
    private const TRIM_PERCENT          = 0.1; // 10% over/under
    private const SEASONALITY_DEFAULT   = 1.0;
    private const MIN_ARTICLE_DAYS      = 365;
    private const FALLBACK_MIN_DAYS     = 10;

    public function getQuantityToOrder(Article $article, int $foresightDays): int // TODO: Return array for default/inner/master when done with function
    {
        $today = new DateTimeImmutable();
        $horizonEnd = $today->add(new DateInterval("P{$foresightDays}D"));

        /* ---------- 1. Articlecluster ---------- */
        $articleCluster = array_merge(
            [$article],
            $this->getLegacyArticles($article)
        );


        /* ---------- 2. History & stockdata ---------- */
        $from180 = $today->sub(new DateInterval('P180D'));

        // Daily total sales (historical)
        $daily = $this->getDailyTotals(
            $articleCluster, $from180, $today
        );

        // Planned sales orders within the foresight horizon
        $plannedOrdersQty = $this->getPlannedOrderQty(
            $articleCluster, $today, $horizonEnd, $daily
        );

        $stockOnHand = $this->getOnHandQty($articleCluster);
        $incomingPOQty = $this->getIncomingQty($articleCluster, $today, $horizonEnd);


        /* ---------- 3. Outlier-filtering ---------- */
        $cleanDaily = $this->removeOutliersAndFlags($daily);


        /* ---------- 4. Trimmed means ---------- */
        $avg60 = $this->trimmedMean($cleanDaily, 60);
        $avg90 = $this->trimmedMean($cleanDaily, 90);
        $avg180 = $this->trimmedMean($cleanDaily, 180);

        // Last years corresponding 90-day period
        $lyStart = (clone $today)->sub(new DateInterval('P1Y'))->sub(new DateInterval('P90D'));
        $lyDaily = $this->getDailyTotals($articleCluster, $lyStart, $lyStart->add(new DateInterval('P90D')));
        $avgLY90 = $this->trimmedMean($this->removeOutliersAndFlags($lyDaily), 90);


        /* ---------- 5. Trend ---------- */
        $articleAgeDays = (new DateTimeImmutable($article->created_at))->diff($today)->days;
        $trend = $this->computeTrend($article, $articleAgeDays, $avg60, $avg90, $avg180, $avgLY90);
    }

    private function removeOutliersAndFlags(array $dailyTotals): array
    {
        $filtered = array_filter(
            $dailyTotals,
            fn (DaySale $row) => !$row->exclude_from_trend || $row->override_include
        );

        // Z-score-test
        $values = array_column($filtered, 'qty');
        $mean = array_sum($values) / max(count($values), 1);
        $std = sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / max(count($values), 1));

        return array_filter(
            $filtered,
            fn (DaySale $row) => $std === 0.0 || abs(($row->qty - $mean) / $std) <= self::ZSCORE_LIMIT
        );
    }

    private function trimmedMean(array $dailyTotals, int $days): float
    {
        if ($days === 0) return 0.0;
        $slice = array_slice($dailyTotals, -$days, $days, true);
        if (!$slice) return 0.0;

        // Sort quantities
        $values = array_values(array_column($slice, 'qty'));
        sort($values);

        $cut = (int) floor(count($values) * self::TRIM_PERCENT);
        $trimmed = array_slice($values, $cut, count($values) - 2 * $cut);

        return array_sum($trimmed) / max(count($trimmed), 1);
    }

    private function computeTrend(
        Article $article,
        int $articleAgeDays,
        float $avg60,
        float $avg90,
        float $avg180,
        float $avgLY90
    ): float {
        // A) Standard formulas
        if ($articleAgeDays > self::MIN_ARTICLE_DAYS && $avgLY90 > 0) {
            $trend = $avg90 / $avgLY90;
        } elseif ($articleAgeDays <= self::MIN_ARTICLE_DAYS && $avg180 > 0) {
            $trend = $avg60 / $avg180;
        } else {
            $trend = null;
        }

        // B) Fallback
        if ($trend === null || $trend === 0.0) {
            $trend = $this->fallbackTrend($article);
        }

        // C) Weighted trend if configured
        $trend = $this->applyWeighting($article, $trend);

        return max($trend, 0.1); // Protect against negative trends
    }












    /* -------------- SALES SERVICE -------------- */
    public function getDailyTotals(array $articles, DateTimeInterface $from, DateTimeInterface $to, bool $includePlanned = false): array
    {
        // return DaySale[]
        return [];
    }

    public function getPlannedOrderQty(array $articles, DateTimeInterface $from, DateTimeInterface $to): int
    {
        return 0;
    }



    /* -------------- INVENTORY SERVICE -------------- */
    public function getOnHandQty(array $article): int
    {
        return 0;
    }

    public function getIncomingQty(array $article, DateTimeInterface $from, DateTimeInterface $to): int
    {
        return 0;
    }


    /* -------------- INVENTORY SERVICE -------------- */
    public function fallbackTrend(Article $article): float
    {
        return 0.0;
    }

    public function applyWeighting(Article $article, float $trend): float
    {
        return $trend;
    }












    private function getLegacyArticles(Article $article): array
    {
        $articles = [];

        if ($article->predecessor) {
            $legacyArticleQuery = Article::where('article_number', $article->predecessor);

            if ($legacyArticleQuery->exists()) {
                $articles[] = $legacyArticleQuery->first();
            }
        }

        return $articles;
    }
}


class DaySale
{
    public function __construct(
        public string $date, // YYYY-MM-DD
        public int $qty,
        public bool $exclude_from_trend = false,
        public bool $override_include = false,
    ) {}
}
