<?php

namespace App\Services;

use App\Models\Article;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Supplier;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class PurchasePlannerV2
{
    /* ---------------------- TUNABLE CONSTANTS ---------------------- */
    private const ZSCORE_LIMIT = 3.0;
    private const OUTLIER_INCLUDE_BOUNDARY = true;  // |z|==limit inkluderas (true) eller exkluderas (false)

    private const TRIM_PERCENT = 0.10; // 10% over/under
    private const SEASONALITY_DEFAULT = 1.0;
    private const MIN_ARTICLE_DAYS = 365;
    private const FALLBACK_MIN_DAYS = 10;
    private const DEFAULT_TREND = 1.0;

    // Trendblandning + clamp (för stabilitet)
    private const SHORT_TREND_WEIGHT = 0.7; // 0..1 (ex 0.7 kort sikt, 0.3 lång sikt)
    private const CLAMP_MIN_TREND    = 0.3;
    private const CLAMP_MAX_TREND    = 1.5;

    /* =============================================================== */

    public function getQuantityToOrder(Article $article, int $foresightDays): array
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

        // Planerade kundordrar inom horisonten (via inköpsdeadline = leverans - snittleveranstid)
        $plannedOrdersQty = $this->getPlannedOrderQtyWithinHorizon(
            $articleCluster, $today, $horizonEnd
        );

        $stockOnHand   = $this->getOnHandQty($articleCluster);
        $incomingPOQty = $this->getIncomingQty($articleCluster, $today, $horizonEnd);
        $reservedQty   = $this->getReservedQty($articleCluster); // (pågående/allokerat)

        /* ---------- 3. Outlier-filtering ---------- */
        $cleanDaily = $this->removeOutliersAndFlags($daily);

        /* ---------- 4. Trimmed per-day avgs (trim-summa / fönstrets dagar) ---------- */
        $avg60  = $this->trimmedAvgPerDay($cleanDaily, 60);
        $avg90  = $this->trimmedAvgPerDay($cleanDaily, 90);
        $avg180 = $this->trimmedAvgPerDay($cleanDaily, 180);

        // Förra årets motsvarande 90-dagarsperiod
        $lyStart  = (clone $today)->sub(new DateInterval('P1Y'))->sub(new DateInterval('P90D'));
        $lyDaily  = $this->getDailyTotals($articleCluster, $lyStart, $lyStart->add(new DateInterval('P90D')));
        $avgLY90  = $this->trimmedAvgPerDay($this->removeOutliersAndFlags($lyDaily), 90);

        /* ---------- 5. Trend ---------- */
        $articleAgeDays = (new DateTimeImmutable($article->publish_at ?: $article->created_at))->diff($today)->days;
        $trend = $this->computeTrend($article, $articleAgeDays, $avg60, $avg90, $avg180, $avgLY90);

        /* ---------- 6. Säsongsindex ---------- */
        $seasonIdx = $this->getWeightedIndex($article, $today, $foresightDays);

        /* ---------- 7. Behovsformel ---------- */
        $baseNeed        = $avg60 * $foresightDays * $trend * $seasonIdx;
        $needPlusOrder   = $baseNeed + $plannedOrdersQty;
        $availableSupply = $stockOnHand + $incomingPOQty - $reservedQty;
        $finalNeed       = max(0.0, $needPlusOrder - $availableSupply);

        /* ---------- 8. Tillväxt & packrundning ---------- */
        $growthFactor = $this->getGrowthPct($article->supplier) ?? 1.0;
        $finalNeed = max(0.0, $finalNeed * $growthFactor);

        $finalNeed = round($finalNeed);

        $innerSize  = max(1, (int)($article->inner_box  ?? 1));   // KORREKT fält
        $masterSize = max(1, (int)($article->master_box ?? 1));

        $finalNeedInnerBox  = round($finalNeed / $innerSize)  * $innerSize;
        $finalNeedMasterBox = round($finalNeed / $masterSize) * $masterSize;

        return [
            'quantity' => $finalNeed,
            'inner'    => $finalNeedInnerBox,
            'master'   => $finalNeedMasterBox,
            'debug'    => [
                'avg60' => $avg60,
                'avg90' => $avg90,
                'avg180'=> $avg180,
                'avgLY90' => $avgLY90,
                'trend' => $trend,
                'seasonIdx' => $seasonIdx,
                'plannedOrdersQty' => $plannedOrdersQty,
                'stockOnHand' => $stockOnHand,
                'incomingPOQty' => $incomingPOQty,
                'reservedQty' => $reservedQty,
                'baseNeed' => $baseNeed,
                'availableSupply' => $availableSupply,
            ],
        ];
    }

    /* ------------------------ OUTLIERS ------------------------ */

    private function removeOutliersAndFlags(array $dailyTotals): array
    {
        $filtered = array_filter(
            $dailyTotals,
            fn(DaySale $row) => !$row->exclude_from_trend || $row->override_include
        );

        $values = array_map(fn (DaySale $r) => (float)$r->qty, $filtered);
        $n = max(count($values), 1);
        $mean = array_sum($values) / $n;
        $var  = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / $n;
        $std  = sqrt($var);

        if ($std == 0.0 || is_nan($std)) {
            return $filtered;
        }

        $limit = self::ZSCORE_LIMIT;
        $cmp = self::OUTLIER_INCLUDE_BOUNDARY
            ? fn(float $z) => $z <= $limit
            : fn(float $z) => $z <  $limit;

        return array_filter(
            $filtered,
            fn(DaySale $row) => $row->override_include || $cmp(abs(($row->qty - $mean) / $std))
        );
    }

    /* ------------------------ AVERAGES ------------------------ */

    private function trimmedAvgPerDay(array $dailyTotals, int $days, float $trimPercent = self::TRIM_PERCENT): float
    {
        if ($days <= 0) return 0.0;

        // Sista $days
        $slice = array_slice($dailyTotals, -$days, $days, true);
        if (!$slice) return 0.0;

        $values = array_values(array_map(fn (DaySale $r) => (float)$r->qty, $slice));
        sort($values);

        $n   = count($values);
        $cut = (int)floor($n * $trimPercent);
        $trimmed = ($cut * 2 >= $n) ? $values : array_slice($values, $cut, $n - 2 * $cut);

        // Viktigt: dela trimmad summa på HELA fönstrets dagar (ej count($trimmed))
        $sum = array_sum($trimmed);
        return $sum / max($days, 1);
    }

    /* ------------------------ TREND ------------------------ */

    private function computeTrend(
        Article $article,
        int     $articleAgeDays,
        float   $avg60,
        float   $avg90,
        float   $avg180,
        float   $avgLY90
    ): float
    {
        // Blandad trend: kort (avg90/avgLY90) och lång (avg60/avg180)
        $trend = $this->blendedTrend($avg60, $avg90, $avg180, $avgLY90, self::SHORT_TREND_WEIGHT);

        // Fallback till enkla regler om blend saknas
        if ($trend === null) {
            if ($articleAgeDays > self::MIN_ARTICLE_DAYS && $avgLY90 > 0) {
                $trend = $avg90 / $avgLY90;
            } elseif ($articleAgeDays <= self::MIN_ARTICLE_DAYS && $avg180 > 0) {
                $trend = $avg60 / $avg180;
            } else {
                $trend = $this->fallbackTrend($article, $articleAgeDays);
            }
        }

        // Clamp + ev. leverantör/vare-korregering i hooken
        $trend = $this->clamp($trend, self::CLAMP_MIN_TREND, self::CLAMP_MAX_TREND);
        $trend = $this->applyWeighting($article, $trend);

        return max($trend, 0.1); // skydd
    }

    private function blendedTrend(
        float $avg60,
        float $avg90,
        float $avg180,
        float $avgLY90,
        float $shortWeight
    ): ?float
    {
        $short = null; $long = null;

        if ($avgLY90 > 0.0 && $avg90 >= 0.0) {
            $short = $avg90 / $avgLY90;
        }
        if ($avg180 > 0.0 && $avg60 >= 0.0) {
            $long = $avg60 / $avg180;
        }

        if ($short === null && $long === null) return null;
        if ($short !== null && $long !== null) {
            return $shortWeight * $short + (1.0 - $shortWeight) * $long;
        }
        return $short ?? $long;
    }

    private function clamp(float $x, float $lo, float $hi): float
    {
        return max($lo, min($hi, $x));
    }

    public function fallbackTrend(Article $article, int $articleAgeDays): float
    {
        // Default trend om artikeln är äldre än X dagar
        if ($article->publish_at && $articleAgeDays > self::FALLBACK_MIN_DAYS) {
            return self::DEFAULT_TREND;
        }

        // Annars: försök med ersatt/legacy
        $legacyArticles = $this->getLegacyArticles($article);
        if (count($legacyArticles) > 0) {
            $legacyArticle = $legacyArticles[0];

            $today = new DateTimeImmutable();
            $from180 = $today->sub(new DateInterval('P180D'));

            $legacyArticleAgeDays = (new DateTimeImmutable($legacyArticle->publish_at ?: $legacyArticle->created_at))->diff($today)->days;

            $daily = $this->getDailyTotals([$legacyArticle], $from180, $today);
            $cleanDaily = $this->removeOutliersAndFlags($daily);

            $avg60  = $this->trimmedAvgPerDay($cleanDaily, 60);
            $avg90  = $this->trimmedAvgPerDay($cleanDaily, 90);
            $avg180 = $this->trimmedAvgPerDay($cleanDaily, 180);

            $lyStart = (clone $today)->sub(new DateInterval('P1Y'))->sub(new DateInterval('P90D'));
            $lyDaily = $this->getDailyTotals([$legacyArticle], $lyStart, $lyStart->add(new DateInterval('P90D')));
            $avgLY90 = $this->trimmedAvgPerDay($this->removeOutliersAndFlags($lyDaily), 90);

            return $this->computeTrend($legacyArticle, $legacyArticleAgeDays, $avg60, $avg90, $avg180, $avgLY90);
        }

        return self::DEFAULT_TREND;
    }

    public function applyWeighting(Article $article, float $trend): float
    {
        // Hook för artikel-/leverantörsspecifik viktning
        return $trend;
    }

    /* ------------------------ SALES SERVICE ------------------------ */

    public function getDailyTotals(array $articles, DateTimeInterface $from, DateTimeInterface $to, bool $includePlanned = false): array
    {
        $articleNumbers = $this->getArticleNumbers($articles);

        $rows = SalesOrder::query()
            ->join('sales_order_lines as sol', 'sol.sales_order_id', '=', 'sales_orders.id')
            ->whereIn('sol.article_number', $articleNumbers)
            ->whereBetween(DB::raw('DATE(sales_orders.date)'), [
                (DateTimeImmutable::createFromInterface($from))->format('Y-m-d'),
                (DateTimeImmutable::createFromInterface($to))->format('Y-m-d'),
            ])
            ->selectRaw('DATE(sales_orders.date) as day, SUM(sol.quantity) as total_qty')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $perDay = $rows->pluck('total_qty', 'day');

        $daySales = [];
        $fromImm = DateTimeImmutable::createFromInterface($from);
        $toImm   = DateTimeImmutable::createFromInterface($to);

        for ($date = $fromImm; $date <= $toImm; $date = $date->add(new DateInterval('P1D'))) {
            $dateString = $date->format('Y-m-d');
            $quantity = (int)($perDay[$dateString] ?? 0);
            $daySales[] = new DaySale($dateString, $quantity);
        }

        return $daySales;
    }

    /**
     * Begränsa planerade kundorder till de rader vars inköpsdeadline hamnar i horisonten.
     * Deadline = requested_delivery - avg_lead_time_days (per artikel).
     */
    public function getPlannedOrderQtyWithinHorizon(array $articles, DateTimeInterface $today, DateTimeInterface $horizonEnd): int
    {
        $articleNumbers = $this->getArticleNumbers($articles);

        $rows = SalesOrderLine::query()
            ->join('sales_orders', 'sales_order_lines.sales_order_id', '=', 'sales_orders.id')
            ->whereIn('sales_order_lines.article_number', $articleNumbers)
            ->where('sales_orders.status', '!=', 'Hold')
            ->select([
                'sales_order_lines.quantity_open as qty',
                'sales_orders.date as requested_delivery',
                'sales_order_lines.article_number as art',
            ])
            ->get();

        $todayImm = DateTimeImmutable::createFromInterface($today);
        $sum = 0;

        foreach ($rows as $r) {
            $req = $r->requested_delivery ? new DateTimeImmutable($r->requested_delivery) : null;

            /** @var Article $a */
            $a = collect($articles)->firstWhere('article_number', $r->art);
            $lt = (int)($a->avg_lead_time_days ?? 0);

            $deadline = $req ? $req->sub(new DateInterval('P'.$lt.'D')) : $todayImm;

            if ($deadline > $todayImm && $deadline <= $horizonEnd) {
                $sum += (int)$r->qty;
            }
        }

        return (int)$sum;
    }

    /* ------------------------ INVENTORY SERVICE ------------------------ */

    public function getOnHandQty(array $articles): int
    {
        $stock = 0;
        foreach ($articles as $article) {
            $stock += (int)($article->stock_on_hand ?? 0);
        }
        return $stock;
    }

    public function getIncomingQty(array $articles, DateTimeInterface $from, DateTimeInterface $to): int
    {
        $articleNumbers = $this->getArticleNumbers($articles);

        return (int)PurchaseOrderLine::whereIn('article_number', $articleNumbers)
            ->select(DB::raw('SUM(quantity - quantity_received) as total'))
            ->where(function ($query) use ($to) {
                $query->whereNull('promised_date')
                    ->orWhereDate('promised_date', '<=', $to->format('Y-m-d'));
            })
            ->value('total');
    }

    private function getReservedQty(array $articles): int
    {
        $articleNumbers = $this->getArticleNumbers($articles);

        return 0;
    }

    /* ------------------------ SEASON SERVICE ------------------------ */

    public function getWeightedIndex(Article $article, DateTimeInterface $start, int $days): float
    {
        if ($days <= 0) {
            return self::SEASONALITY_DEFAULT;
        }

        $start = DateTimeImmutable::createFromInterface($start);
        $end   = $start->add(new DateInterval("P{$days}D"));

        // 1) WEEKLY
        $weekly = $this->getWeeklyIndexForArticle($article);
        if ($this->hasEnoughWeekly($weekly)) {
            $idx = $this->normalizeWeekly($weekly['index']);
            return $this->weightByWeeks($start, $end, $idx);
        }

        // 2) MONTHLY
        $monthly = $this->getMonthlyIndexForArticle($article);
        if ($this->hasEnoughMonthly($monthly)) {
            $idx = $this->normalizeMonthly($monthly['index']);
            return $this->weightByMonths($start, $end, $idx);
        }

        // 3) Neutral
        return self::SEASONALITY_DEFAULT;
    }

    public function getWeeklyIndexForArticle(Article $article): ?array
    {
        $bounds = $this->getBounds($article);
        if (!$bounds) return null;
        [$minDt, $maxDt] = $bounds;

        $dayRows = DB::table('sales_orders')
            ->join('sales_order_lines', 'sales_order_lines.sales_order_id', '=', 'sales_orders.id')
            ->selectRaw('DATE(sales_orders.date) AS d, SUM(sales_order_lines.quantity) AS qty')
            ->where('sales_order_lines.article_number', $article->article_number)
            ->whereBetween(DB::raw('DATE(sales_orders.date)'), [$minDt, $maxDt])
            ->groupBy(DB::raw('DATE(sales_orders.date)'))
            ->get();

        $perWeekQty = [];
        $perIsoYearQty = [];
        foreach ($dayRows as $r) {
            $d  = new DateTimeImmutable($r->d);
            $yw = $d->format('o-\WW'); // t.ex. 2025-W08
            $oy = $d->format('o');
            $q  = (int)$r->qty;
            $perWeekQty[$yw]    = ($perWeekQty[$yw] ?? 0) + $q;
            $perIsoYearQty[$oy] = ($perIsoYearQty[$oy] ?? 0) + $q;
        }

        $min = new DateTimeImmutable($minDt);
        $max = new DateTimeImmutable($maxDt);

        $daysToMonday = (int)$min->format('N') - 1; // Mon=1..Sun=7
        $cursor = $min->sub(new DateInterval('P'.$daysToMonday.'D'));

        $perIsoYearDays = []; // 'YYYY' => days
        $perYW = [];          // 'YYYY-WW' => info

        while ($cursor <= $max) {
            $weekStart = $cursor;
            $weekEnd   = $cursor->add(new DateInterval('P6D'));

            $realStart = $weekStart < $min ? $min : $weekStart;
            $realEnd   = $weekEnd   > $max ? $max : $weekEnd;
            if ($realEnd < $realStart) {
                $cursor = $cursor->add(new DateInterval('P7D'));
                continue;
            }

            $days    = $realStart->diff($realEnd)->days + 1;
            $isoYear = $weekStart->format('o');
            $isoWeek = $weekStart->format('W');
            $key     = $isoYear.'-W'.$isoWeek;

            $qty = $perWeekQty[$key] ?? 0;
            $perYW[$key] = ['days'=>$days,'qty'=>$qty,'week'=>(int)$isoWeek,'year'=>$isoYear];

            $perIsoYearDays[$isoYear] = ($perIsoYearDays[$isoYear] ?? 0) + $days;

            $cursor = $cursor->add(new DateInterval('P7D'));
        }

        $years = count($perIsoYearDays);
        if ($years < 2) return null;

        $perWeekIdx = []; // 1..53 => [idx...]
        foreach ($perYW as $info) {
            $isoYear = $info['year'];
            $w       = $info['week'];
            $days    = $info['days'];
            $qty     = $info['qty'];

            $yearDays = $perIsoYearDays[$isoYear] ?? 0;
            $yearQty  = $perIsoYearQty[$isoYear]  ?? 0;
            if ($days <= 0 || $yearDays <= 0 || $yearQty <= 0) continue;

            $weekRate = $qty / $days;
            $yearRate = $yearQty / $yearDays;

            $idx = $yearRate > 0 ? ($weekRate / $yearRate) : 1.0;
            $perWeekIdx[$w][] = $idx;
        }

        $curve = [];
        for ($w = 1; $w <= 53; $w++) {
            $vals = $perWeekIdx[$w] ?? [];
            $curve[$w] = $vals ? array_sum($vals)/count($vals) : 1.0;
        }
        $mean52 = array_sum(array_slice($curve, 0, 52, true)) / 52.0 ?: 1.0;
        foreach ($curve as $w => $v) {
            $curve[$w] = $v / $mean52;
        }

        $weeksPresent = count(array_filter(array_keys($curve), fn($w) => isset($perWeekIdx[$w])));
        if ($weeksPresent < 48) return null;

        return ['years' => $years, 'index' => $curve];
    }

    public function getMonthlyIndexForArticle(Article $article): ?array
    {
        $bounds = $this->getBounds($article);
        if (!$bounds) return null;
        [$minDt, $maxDt] = $bounds;

        $dayRows = DB::table('sales_orders')
            ->join('sales_order_lines', 'sales_order_lines.sales_order_id', '=', 'sales_orders.id')
            ->selectRaw('DATE(sales_orders.date) AS d, SUM(sales_order_lines.quantity) AS qty')
            ->where('sales_order_lines.article_number', $article->article_number)
            ->whereBetween(DB::raw('DATE(sales_orders.date)'), [$minDt, $maxDt])
            ->groupBy(DB::raw('DATE(sales_orders.date)'))
            ->get();

        $perMonthQty = [];
        $perYearQty  = [];
        foreach ($dayRows as $r) {
            $d  = new DateTimeImmutable($r->d);
            $ym = $d->format('Y-m');
            $y  = $d->format('Y');
            $q  = (int) $r->qty;
            $perMonthQty[$ym] = ($perMonthQty[$ym] ?? 0) + $q;
            $perYearQty[$y]   = ($perYearQty[$y]   ?? 0) + $q;
        }

        $cursor  = new DateTimeImmutable(substr($minDt, 0, 7) . '-01');
        $end     = (new DateTimeImmutable(substr($maxDt, 0, 7) . '-01'))->add(new DateInterval('P1M'));
        $minDate = new DateTimeImmutable($minDt);
        $maxDate = new DateTimeImmutable($maxDt);

        $perYearDays = [];
        $perYM       = [];
        while ($cursor < $end) {
            $y  = $cursor->format('Y');
            $ym = $cursor->format('Y-m');

            $monthStart = $cursor;
            $nextMonth  = $monthStart->add(new DateInterval('P1M'));
            $monthEnd   = $nextMonth->sub(new DateInterval('P1D'));

            $realStart = $monthStart < $minDate ? $minDate : $monthStart;
            $realEnd   = $monthEnd   > $maxDate ? $maxDate : $monthEnd;
            if ($realEnd < $realStart) {
                $cursor = $cursor->add(new DateInterval('P1M'));
                continue;
            }

            $days = $realStart->diff($realEnd)->days + 1;

            $qty = $perMonthQty[$ym] ?? 0;
            $perYM[$ym] = ['days' => $days, 'qty' => $qty];

            $perYearDays[$y] = ($perYearDays[$y] ?? 0) + $days;

            $cursor = $cursor->add(new DateInterval('P1M'));
        }

        $years = count($perYearDays);
        if ($years < 2) return null; // kräver >= 2 år

        $perMonthIdx = [];
        foreach ($perYM as $ym => $info) {
            [$y, $m] = explode('-', $ym);
            $days = $info['days'];
            $qty  = $info['qty'];
            if ($days <= 0 || ($perYearDays[$y] ?? 0) <= 0) continue;

            $monthRate = $qty / $days;
            $yearRate  = ($perYearQty[$y] ?? 0) / $perYearDays[$y];
            if ($yearRate <= 0) continue;

            $perMonthIdx[(int)$m][] = $monthRate / $yearRate;
        }

        $curve = [];
        for ($m = 1; $m <= 12; $m++) {
            $vals = $perMonthIdx[$m] ?? [];
            $curve[$m] = $vals ? array_sum($vals)/count($vals) : 1.0;
        }
        $mean = array_sum($curve) / 12.0 ?: 1.0;
        foreach ($curve as $k => $v) {
            $curve[$k] = $v / $mean;
        }

        $monthsPresent = count($perYM);
        if ($monthsPresent < 10) return null;

        return ['years' => $years, 'index' => $curve];
    }

    public function getBounds(Article $article): ?array
    {
        $b = DB::table('sales_orders')
            ->join('sales_order_lines', 'sales_order_lines.sales_order_id', '=', 'sales_orders.id')
            ->selectRaw('MIN(DATE(sales_orders.date)) AS min_dt, MAX(DATE(sales_orders.date)) AS max_dt')
            ->where('sales_order_lines.article_number', $article->article_number)
            ->first();

        if (!$b || !$b->min_dt || !$b->max_dt) return null;

        return [$b->min_dt, $b->max_dt];
    }

    public function hasEnoughWeekly(?array $curve): bool
    {
        if (!$curve) return false;
        $years = (int) ($curve['years'] ?? 0);
        $cnt = is_array($curve['index'] ?? null) ? count($curve['index']) : 0;
        return $years >= 2 && $cnt >= 48;
    }

    public function hasEnoughMonthly(?array $curve): bool
    {
        if (!$curve) return false;
        $years = (int) ($curve['years'] ?? 0);
        $cnt = is_array($curve['index'] ?? null) ? count($curve['index']) : 0;
        return $years >= 2 && $cnt >= 10;
    }

    public function normalizeWeekly(array $w): array
    {
        for ($i = 1; $i <= 52; $i++) {
            if (!array_key_exists($i, $w)) $w[$i] = 1.0;
        }
        ksort($w);
        $baseWeeks = array_slice($w, 0, 52, true);
        $den   = count($baseWeeks) ?: 1;
        $mean  = array_sum($baseWeeks) / $den ?: 1.0;
        foreach ($w as $k => $v) {
            $w[$k] = $v / $mean;
        }
        return $w;
    }

    public function normalizeMonthly(array $m): array
    {
        for ($i = 1; $i <= 12; $i++) {
            if (!array_key_exists($i, $m)) $m[$i] = 1.0;
        }
        ksort($m);
        $mean = array_sum($m) / 12.0 ?: 1.0;
        foreach ($m as $k => $v) {
            $m[$k] = $v / $mean;
        }
        return $m;
    }

    public function weightByWeeks(DateTimeImmutable $start, DateTimeImmutable $end, array $weekIdx): float
    {
        $counts = [];
        for ($d = $start; $d < $end; $d = $d->add(new DateInterval('P1D'))) {
            $w = (int)$d->format('W'); // ISO 01..53
            $counts[$w] = ($counts[$w] ?? 0) + 1;
        }

        $weighted = 0.0;
        $total    = 0;
        foreach ($counts as $w => $days) {
            $idx = $weekIdx[$w] ?? 1.0;
            $weighted += $idx * $days;
            $total    += $days;
        }

        return $total > 0 ? ($weighted / $total) : 1.0;
    }

    public function weightByMonths(DateTimeImmutable $start, DateTimeImmutable $end, array $monthIdx): float
    {
        $cursor    = $start;
        $weighted  = 0.0;
        $totalDays = 0;

        while ($cursor < $end) {
            $monthStart = new DateTimeImmutable($cursor->format('Y-m-01'));
            $nextMonth  = $monthStart->add(new DateInterval('P1M'));
            $segmentEnd = $nextMonth < $end ? $nextMonth : $end;

            $days = $cursor->diff($segmentEnd)->days;
            if ($days <= 0) break;

            $m   = (int)$cursor->format('n'); // 1..12
            $idx = $monthIdx[$m] ?? 1.0;

            $weighted  += $idx * $days;
            $totalDays += $days;
            $cursor     = $segmentEnd;
        }

        return $totalDays > 0 ? ($weighted / $totalDays) : 1.0;
    }

    /* ------------------------ SUPPLIER SERVICE ------------------------ */

    public function getGrowthPct(Supplier $supplier): ?float // ex. 1.05 = +5 %
    {
        $multiplier = 1.0;
        if ($supplier->calculated_growth) {
            $multiplier += $supplier->calculated_growth / 100;
        }
        return $multiplier;
    }

    /* ------------------------ ARTICLE HELPERS ------------------------ */

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

    private function getArticleNumbers(array $articles): array
    {
        $articleNumbers = [];
        foreach ($articles as $article) {
            $articleNumbers[] = $article->article_number;
        }
        return $articleNumbers;
    }
}

/* ------------------------ VALUE OBJECT ------------------------ */

class DaySale
{
    public function __construct(
        public string $date, // YYYY-MM-DD
        public int    $qty,
        public bool   $exclude_from_trend = false,
        public bool   $override_include   = false,
    ) {}
}
