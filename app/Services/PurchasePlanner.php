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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PurchasePlanner
{
    private const LOG_DATA = true;
    private const LOG_ARTICLES = ['12-1915'];

	private const ZSCORE_LIMIT = 3.0;
	private const TRIM_PERCENT = 0.0; // 0.1 = 10% over/under
	private const SEASONALITY_DEFAULT = 1.0;
	private const MIN_ARTICLE_DAYS = 365;
	private const FALLBACK_MIN_DAYS = 10;
	private const DEFAULT_TREND = 1.0;

    private array $log = [];

	public function getQuantityToOrder(Article $article, int $foresightDays): array
	{
		$today = new DateTimeImmutable();
		$horizonEnd = $today->add(new DateInterval("P{$foresightDays}D"));


        // Special handling for drop-shipped articles
        if ($article->is_dropship) {
            $onOrder = ArticleQuantityCalculator::getOnOrder($article->article_number);

            return [
                'quantity' => $onOrder,
                'inner' => $onOrder,
                'master' => $onOrder,
            ];
        }


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
			$articleCluster, $today, $horizonEnd
		);

		$stockOnHand = $this->getOnHandQty($articleCluster);
		$incomingPOQty = $this->getIncomingQty($articleCluster, $today, $horizonEnd);

        $this->addLog('$plannedOrdersQty = ' . $plannedOrdersQty);
        $this->addLog('$stockOnHand = ' . $stockOnHand);
        $this->addLog('$incomingPOQty = ' . $incomingPOQty);


		/* ---------- 3. Outlier-filtering ---------- */
		$cleanDaily = $this->removeOutliersAndFlags($daily, $from180, $today);


		/* ---------- 4. Trimmed means ---------- */
		$avg60 = $this->trimmedMean($cleanDaily, 60);
		$avg90 = $this->trimmedMean($cleanDaily, 90);
		$avg180 = $this->trimmedMean($cleanDaily, 180);

		// Last years corresponding 90-day period
		$lyStart = (clone $today)->sub(new DateInterval('P1Y'))->sub(new DateInterval('P90D'));
		$lyDaily = $this->getDailyTotals($articleCluster, $lyStart, $lyStart->add(new DateInterval('P90D')));
		$avgLY90 = $this->trimmedMean($this->removeOutliersAndFlags($lyDaily, $lyStart, $lyStart), 90);

        $this->addLog('$avg60 = ' . $avg60);
        $this->addLog('$avg90 = ' . $avg90);
        $this->addLog('$avg180 = ' . $avg180);
        $this->addLog('$avgLY90 = ' . $avgLY90);


		/* ---------- 5. Trend ---------- */
		$articleAgeDays = (new DateTimeImmutable($article->publish_at ?: $article->created_at))->diff($today)->days;
		$trend = $this->computeTrend($article, $articleAgeDays, $avg60, $avg90, $avg180, $avgLY90);

        $this->addLog('$trend = ' . $trend);

		/* ---------- 6. Season index ---------- */
		$seasonIdx = $this->getWeightedIndex($article, $today, $foresightDays);

        $this->addLog('$seasonIdx = ' . $seasonIdx);


		/* ---------- 7. Needs formula ---------- */
		$baseNeed = $avg60 * $foresightDays * $trend * $seasonIdx;
		$needPlusOrder = $baseNeed + $plannedOrdersQty;
		$availableSupply = $stockOnHand + $incomingPOQty;
		$finalNeed = max(0, $needPlusOrder - $availableSupply);

        $this->addLog('$baseNeed = $avg60 * $foresightDays * $trend * $seasonIdx = ' . $baseNeed);
        $this->addLog('$needPlusOrder = $baseNeed + $plannedOrdersQty = ' . $needPlusOrder);
        $this->addLog('$availableSupply = $stockOnHand + $incomingPOQty = ' . $availableSupply);
        $this->addLog('$finalNeed = $needPlusOrder - $availableSupply = ' . $finalNeed);


		/* ---------- 8. Growth & Box Size Adjustment ---------- */
		$growthFactor = $this->getGrowthPct($article->supplier) ?? 1.0;
        $this->addLog('$growthFactor = ' . $growthFactor);

		$finalNeed *= $growthFactor;
		$finalNeed = round($finalNeed);

        $this->addLog('$finalNeed = $finalNeed * $growthFactor = ' . $finalNeed);

		$innerSize = max(1, $article->master_box);
		$masterSize = max(1, $article->master_box);

        $this->addLog('$innerSize = ' . $innerSize);
        $this->addLog('$masterSize = ' . $masterSize);

		$finalNeedInnerBox = round($finalNeed / $innerSize) * $innerSize;
		$finalNeedMasterBox = round($finalNeed / $masterSize) * $masterSize;

        $this->addLog('$finalNeedInnerBox = ' . $finalNeedInnerBox);
        $this->addLog('$finalNeedMasterBox = ' . $finalNeedMasterBox);

        if (self::LOG_DATA && (!self::LOG_ARTICLES || in_array($article->article_number, self::LOG_ARTICLES))) {
            $this->saveLog();
        }

        $this->clearLog();

		return [
			'quantity' => $finalNeed,
			'inner' => $finalNeedInnerBox,
			'master' => $finalNeedMasterBox,
		];
	}

	public function removeOutliersAndFlags(array $dailyTotals, DateTimeInterface $startDate, DateTimeInterface $endDate): array
	{
		$filtered = array_filter(
			$dailyTotals,
			fn(DaySale $row) => !$row->exclude_from_trend || $row->override_include
		);

        if (count($filtered) < 2) {
            return $filtered;
        }

        return $filtered;

        $interval = $startDate->diff($endDate);
        $days = $interval->days;

        $totalSales = array_sum(array_map(fn (DaySale $r) => $r->qty, $filtered));
        $avgPerDay = $totalSales / max($days, 1);

        if ($avgPerDay < 1.0) {
            $percent = 8; // 800%
        } elseif ($avgPerDay < 5.0) {
            $percent = 5; // 500%
        } elseif ($avgPerDay < 10.0) {
            $percent = 3; // 300%
        } else {
            $percent = 1.5; // 150%
        }

        // Sort $filtered by qty
        usort($filtered, fn(DaySale $a, DaySale $b) => $b->qty <=> $a->qty);

        // Check if the sales contains a outlier
        if ($filtered[0]->qty > ($filtered[1]->qty * $percent)) {
            // Remove the first item in the array
            array_shift($filtered);
        }

        return $filtered;
	}

	public function trimmedMean(array $dailyTotals, int $days): float
	{
		if ($days === 0) return 0.0;
		$slice = array_slice($dailyTotals, -$days, $days, true);
		if (!$slice) return 0.0;

		// Sort quantities
		$values = array_values(array_map(fn ($r) => $r->qty, $slice));
		sort($values);

		$cut = (int)floor(count($values) * self::TRIM_PERCENT);
		$trimmed = array_slice($values, $cut, count($values) - 2 * $cut);

		return array_sum($trimmed) / max(count($trimmed), 1);
	}

	public function computeTrend(
		Article $article,
		int     $articleAgeDays,
		float   $avg60,
		float   $avg90,
		float   $avg180,
		float   $avgLY90
	): float
	{
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
			$trend = $this->fallbackTrend($article, $articleAgeDays);
		}

		// C) Weighted trend if configured
		$trend = $this->applyWeighting($article, $trend);

        // If trend is above 2.0 it's probably not correct, so use default trend and rely on growth factor
        if ($trend > 2.0) {
            $trend = self::DEFAULT_TREND;
        }

		return max($trend, 0.1); // Protect against negative trends
	}


	/* -------------- SALES SERVICE -------------- */
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
		$toImm = DateTimeImmutable::createFromInterface($to);

		for ($date = $fromImm; $date <= $toImm; $date = $date->add(new DateInterval('P1D'))) {
			$dateString = $date->format('Y-m-d');

			$quantity = (int)($perDay[$dateString] ?? 0);

			$daySales[] = new DaySale($dateString, $quantity);
		}

		return $daySales;
	}

	public function getPlannedOrderQty(array $articles, DateTimeInterface $from, DateTimeInterface $to): int
	{
		$articleNumbers = $this->getArticleNumbers($articles);

        $qty = 0;

        foreach ($articleNumbers as $articleNumber) {
            $qty += ArticleQuantityCalculator::getOnOrder($articleNumber);
        }

        return $qty;
	}


	/* -------------- INVENTORY SERVICE -------------- */
	public function getOnHandQty(array $articles): int
	{
		$stock = 0;

		foreach ($articles as $article) {
			$stock += $article->stock_on_hand ?? 0;
		}

		return $stock;
	}

	public function getIncomingQty(array $articles, DateTimeInterface $from, DateTimeInterface $to): int
	{
        $articleNumbers = $this->getArticleNumbers($articles);

        $qty = 0;

        foreach ($articleNumbers as $articleNumber) {
            $qty += ArticleQuantityCalculator::getIncoming($articleNumber);
        }

        return $qty;
	}


	/* -------------- TREND SERVICE -------------- */
	public function fallbackTrend(Article $article, int $articleAgeDays): float
	{
		// Use default trend if article is older than X days
		if ($article->publish_at && $articleAgeDays > self::FALLBACK_MIN_DAYS) {
			return self::DEFAULT_TREND;
		}

		// Use trend based on legacy article
		$legacyArticles = $this->getLegacyArticles($article);
		if (count($legacyArticles) > 0) {
			$legacyArticle = $legacyArticles[0];

			$today = new DateTimeImmutable();
			$from180 = $today->sub(new DateInterval('P180D'));

			$legacyArticleAgeDays = (new DateTimeImmutable($legacyArticle->publish_at ?: $legacyArticle->created_at))->diff($today)->days;


			$daily = $this->getDailyTotals(
				[$legacyArticle], $from180, $today
			);

			$cleanDaily = $this->removeOutliersAndFlags($daily, $from180, $today);

			$avg60 = $this->trimmedMean($cleanDaily, 60);
			$avg90 = $this->trimmedMean($cleanDaily, 90);
			$avg180 = $this->trimmedMean($cleanDaily, 180);

			$lyStart = (clone $today)->sub(new DateInterval('P1Y'))->sub(new DateInterval('P90D'));
			$lyDaily = $this->getDailyTotals([$legacyArticle], $lyStart, $lyStart->add(new DateInterval('P90D')));
			$avgLY90 = $this->trimmedMean($this->removeOutliersAndFlags($lyDaily, $lyStart, $lyStart), 90);

			return $this->computeTrend($legacyArticle, $legacyArticleAgeDays, $avg60, $avg90, $avg180, $avgLY90);
		}

		return self::DEFAULT_TREND;
	}

	public function applyWeighting(Article $article, float $trend): float
	{
		return $trend;
	}


	/* -------------- SEASON SERVICE -------------- */
	public function getWeightedIndex(Article $article, DateTimeInterface $start, int $days): float
	{
		if ($days <= 0) {
			return self::SEASONALITY_DEFAULT;
		}

		$start = DateTimeImmutable::createFromInterface($start);
		$end = $start->add(new DateInterval("P{$days}D"));

		// 1) Try WEEKLY
		$weekly = $this->getWeeklyIndexForArticle($article);
		if ($this->hasEnoughWeekly($weekly)) {
			$idx = $this->normalizeWeekly($weekly['index']);
			return $this->weightByWeeks($start, $end, $idx);
		}

		// 2) Try MONTHLY
		$monthly = $this->getMonthlyIndexForArticle($article);
		if ($this->hasEnoughMonthly($monthly)) {
			$idx = $this->normalizeMonthly($monthly['index']);
			return $this->weightByMonths($start, $end, $idx);
		}

		// 3) Nothing usable -> neutral
		return self::SEASONALITY_DEFAULT;
	}

	public function getWeeklyIndexForArticle(Article $article): ?array
	{
		$bounds = $this->getBounds($article);
		if (!$bounds) return null;
		[$minDt, $maxDt] = $bounds;

		// Per-day totals (days with orders only)
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
			$yw = $d->format('o-\WW'); // e.g. 2025-W08
			$oy = $d->format('o');
			$q  = (int)$r->qty;
			$perWeekQty[$yw]    = ($perWeekQty[$yw] ?? 0) + $q;
			$perIsoYearQty[$oy] = ($perIsoYearQty[$oy] ?? 0) + $q;
		}

		// Build ISO-week buckets across [min..max] with exact day counts
		$min = new DateTimeImmutable($minDt);
		$max = new DateTimeImmutable($maxDt);

		$daysToMonday = (int)$min->format('N') - 1; // Mon=1 .. Sun=7
		$cursor = $min->sub(new DateInterval('P'.$daysToMonday.'D'));

		$perIsoYearDays = []; // 'YYYY' => days
		$perYW = [];          // 'YYYY-WW' => ['days'=>..,'qty'=>..,'week'=>..,'year'=>..]

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

		// Compute per-week index (within each ISO year), then average per week number
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

			$idx = $weekRate / $yearRate;
			$perWeekIdx[$w][] = $idx;
		}

		// Build curve 1..53, fill gaps with 1.0, normalize mean of first 52 to 1.0
		$curve = [];
		for ($w = 1; $w <= 53; $w++) {
			$vals = $perWeekIdx[$w] ?? [];
			$curve[$w] = $vals ? array_sum($vals)/count($vals) : 1.0;
		}
		$mean52 = array_sum(array_slice($curve, 0, 52, true)) / 52.0 ?: 1.0;
		foreach ($curve as $w => $v) {
			$curve[$w] = $v / $mean52;
		}

		// Optional: require >=48 distinct weeks
		$weeksPresent = count(array_filter(array_keys($curve), fn($w) => isset($perWeekIdx[$w])));
		if ($weeksPresent < 48) return null;

		return ['years' => $years, 'index' => $curve];
	}

	public function getMonthlyIndexForArticle(Article $article): ?array
	{
		$bounds = $this->getBounds($article);
		if (!$bounds) return null;
		[$minDt, $maxDt] = $bounds;

		// Per-day totals only for the days that actually had orders
		$dayRows = DB::table('sales_orders')
			->join('sales_order_lines', 'sales_order_lines.sales_order_id', '=', 'sales_orders.id')
			->selectRaw('DATE(sales_orders.date) AS d, SUM(sales_order_lines.quantity) AS qty')
			->where('sales_order_lines.article_number', $article->article_number)
			->whereBetween(DB::raw('DATE(sales_orders.date)'), [$minDt, $maxDt])
			->groupBy(DB::raw('DATE(sales_orders.date)'))
			->get();

		// Group per month (YYYY-MM) and per year (YYYY)
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

		// Build month buckets across [minDt..maxDt] with exact day counts
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
			$monthEnd   = (new DateTimeImmutable($cursor->format('Y-m-01')))
				->add(new DateInterval('P1M'))
				->sub(new DateInterval('P1D'));

			// clamp to bounds
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
		if ($years < 2) return null; // need >= 2 yrs

		// Compute month index per (y,m), then average across years per month m=1..12
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

		// Build curve 1..12, fill gaps with 1.0, normalize mean to 1.0
		$curve = [];
		for ($m = 1; $m <= 12; $m++) {
			$vals = $perMonthIdx[$m] ?? [];
			$curve[$m] = $vals ? array_sum($vals)/count($vals) : 1.0;
		}
		$mean = array_sum($curve) / 12.0 ?: 1.0;
		foreach ($curve as $m => $v) {
			$curve[$m] = $v / $mean;
		}

		// Optional: require >=10 distinct months (across the span)
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

		// Heuristic: at least ~2 years and >=48 distinct weeks
		return $years >= 2 && $cnt >= 48;
	}

	public function hasEnoughMonthly(?array $curve): bool
	{
		if (!$curve) return false;

		$years = (int) ($curve['years'] ?? 0);
		$cnt = is_array($curve['index'] ?? null) ? count($curve['index']) : 0;

		// Heuristic: at least ~2 years and >=10 months present
		return $years >= 2 && $cnt >= 10;
	}

	public function normalizeWeekly(array $w): array
	{
		for ($i = 1; $i <= 52; $i++) {
			if (!array_key_exists($i, $w)) $w[$i] = 1.0;
		}
		ksort($w);
		// Normalize to the mean of weeks 1..52 (keep week 53 if present)
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
		$mean = array_sum($m) / 12.0;
		$mean = $mean ?: 1.0;
		foreach ($m as $k => $v) {
			$m[$k] = $v / $mean;
		}
		return $m;
	}

	public function weightByWeeks(DateTimeImmutable $start, DateTimeImmutable $end, array $weekIdx): float
	{
		$counts = []; // weekNo => days
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
			// start of current month
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





    /* -------------- SUPPLIER SERVICE -------------- */
    public function getGrowthPct(Supplier $supplier): ?float // ex. 1.05 = +5 %
    {
		$multiplier = 1;

		if ($supplier->calculated_growth) {
			$multiplier += $supplier->calculated_growth / 100;
		}

        return $multiplier;
    }







    public function getLegacyArticles(Article $article): array
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

	public function getArticleNumbers(array $articles): array
	{
		$articleNumbers = [];
		foreach ($articles as $article) {
			$articleNumbers[] = $article->article_number;
		}

		return $articleNumbers;
	}

    public function addLog(string $message): void
    {
        $this->log[] = $message;
    }

    public function saveLog(): void
    {
        $jsonLog = json_encode($this->log, JSON_PRETTY_PRINT);

        $filename = 'purchase_planner_log_' . now()->format('Y-m-d_H-i-s') . '_' . rand(10000000, 99999999) . '.json';
        $dir = storage_path('logs');

        File::ensureDirectoryExists($dir);
        File::put($dir . DIRECTORY_SEPARATOR . $filename, $jsonLog);
    }

    public function clearLog(): void
    {
        $this->log = [];
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
