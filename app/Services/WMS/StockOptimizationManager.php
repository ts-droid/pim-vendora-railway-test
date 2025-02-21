<?php

namespace App\Services\WMS;

use App\Http\Controllers\ConfigController;
use App\Models\StockItem;
use App\Models\StockItemMovement;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Models\StockPlaceGroup;
use App\Utilities\WarehouseHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class StockOptimizationManager
{
    const MOVEMENT_TYPE_REFILL = 'refill';
    const MOVEMENT_TYPE_ORGANIZATION = 'organization';
    const MOVEMENT_TYPE_UNLEASH = 'unleash';

    const CLASSIFICATION_ORDER = ['A', 'B', 'C'];

    const REFILL_THRESHOLD = 0.5;       // Refill a compartment when occupied volume is below 50%

    private array $config;

    private $movementCache = [];

    public function __construct()
    {
        $this->config = [
            'max_volume_A' => ConfigController::getConfig('max_volume_class_size_a', 100),
            'max_volume_B' => ConfigController::getConfig('max_volume_class_size_b', 100),
            'max_volume_C' => ConfigController::getConfig('max_volume_class_size_c', 100),
            'empty_rest_products_A' => ConfigController::getConfig('wms_empty_rest_products_a', 0),
            'empty_rest_products_B' => ConfigController::getConfig('wms_empty_rest_products_b', 0),
            'empty_rest_products_C' => ConfigController::getConfig('wms_empty_rest_products_c', 0),
            'wms_multi_intelligence_A' => ConfigController::getConfig('wms_multi_intelligence_a', 0),
            'wms_multi_intelligence_B' => ConfigController::getConfig('wms_multi_intelligence_b', 0),
            'wms_multi_intelligence_C' => ConfigController::getConfig('wms_multi_intelligence_c', 0),
            'wms_multi_intelligence_period_A' => ConfigController::getConfig('wms_multi_intelligence_period_a', 7),
            'wms_multi_intelligence_period_B' => ConfigController::getConfig('wms_multi_intelligence_period_b', 7),
            'wms_multi_intelligence_period_C' => ConfigController::getConfig('wms_multi_intelligence_period_c', 7),
        ];
    }

    public function optimize(): bool
    {
        $this->printLine('Starting optimize()');

        $isRunning = ConfigController::getConfig('optimize_stock_running', 0);
        if ($isRunning) {
            $this->printLine('Another process is already running.');
            return false;
        }

        ConfigController::setConfigs(['optimize_stock_running' => 1]);

        $lastWorkTime = StockItemMovement::all()->max('ping_at');
        if ($lastWorkTime > (time() - 60)) {
            // Do not run the operation if someone is working on a stock movement
            $this->printLine('A user is working on a stock movement. Stopping.');
            ConfigController::setConfigs(['optimize_stock_running' => 0]);
            return false;
        }

        $this->printLine('Classifying articles.');
        Artisan::call('articles:classify');

        // Remove all existing StockItemMovements
        $this->printLine('Removing existing stock movements.');
        DB::table('stock_item_movements')->truncate();


        $this->printLine('Fetching articles and stock places.');
        $groupedStockPlaces = $this->getGroupedStockPlaces();
        $groupedArticles = $this->getGroupedArticles();
        $articlesToplist = $this->getArticlesToplist();

        $unleashCompartmentIDs = $this->clearUnleashStatus($groupedStockPlaces);

        $articleStockData = [];

        // First fill all A stock places forcefully
        $this->printLine('Start to forcefully fill all A compartments.');

        $toplistIndex = 0;
        foreach (($groupedStockPlaces['A'] ?? []) as $stockPlace) {
            foreach ($stockPlace->compartments as $compartment) {
                if ($compartment->is_manual || $compartment->is_reserved() || in_array($compartment->id, $unleashCompartmentIDs)) {
                    continue;
                }

                $identifier = $stockPlace->identifier . ':' . $compartment->identifier;
                $this->printLine('Processing ' . $identifier);

                $uniqueStockItems = $compartment->stockItems->pluck('article_number')->unique();
                $totalSections = $compartment->sections->count() ?: 1;

                if ($uniqueStockItems->count() >= $totalSections) {
                    // No empty compartments
                    $this->printLine('No empty compartments.');
                    continue;
                }

                $multiIntelligence = $this->config['wms_multi_intelligence_A'];
                $multiIntelligencePeriod = $this->config['wms_multi_intelligence_period_A'];
                $stockPlaceConfig = $this->getStockPlaceConfig($stockPlace, $compartment, $multiIntelligence, $multiIntelligencePeriod);

                $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                $maxVolume = $compartmentVolume * ($stockPlaceConfig['max_volume'] / 100);

                $sectionVolume = $compartmentVolume / $totalSections;
                $maxSectionVolume = $sectionVolume * ($stockPlaceConfig['max_volume'] / 100);

                $occupiedVolumeOverall = 0;
                foreach ($compartment->stockItems as $stockItem) {
                    $stockItemVolume = ($stockItem->article->height / 1000) * ($stockItem->article->width / 1000) * ($stockItem->article->depth / 1000);
                    $occupiedVolumeOverall += $stockItemVolume;
                }

                $emptySections = $totalSections - $uniqueStockItems->count();

                for ($i = 0;$i < $emptySections;$i++) {
                    $failCount = 0;

                    $this->printLine('Processing compartment index ' . $i);

                    while ($failCount < 100) {
                        $article = $articlesToplist[$toplistIndex] ?? null;
                        if (!$article) {
                            $this->printLine('No more articles found to fill.');
                            break 3; // No more articles to fill
                        }

                        $this->printLine('Selecting article: ' . $article['article_number']);

                        if (!isset($articleStockData[$article['article_number']])) {
                            $articleStockData[$article['article_number']] = [
                                'stock' => $article->stock,
                                'managedStock' => 0,
                                'has_a_placement' => WarehouseHelper::articleHasPlacement($article['article_number'], ['A']),
                                'has_main_placement' => WarehouseHelper::articleHasPlacement($article['article_number'], ['A', 'B']),
                            ];
                        }

                        $stockData = &$articleStockData[$article['article_number']];

                        if ($stockData['has_a_placement']) {
                            $toplistIndex++;
                            $failCount++;
                            $this->printLine('Article already have placement in an A compartment');
                            continue;
                        }

                        $articleVolume = ($article['height'] / 1000) * ($article['width'] / 1000) * ($article['depth'] / 1000);

                        $freeVolume = min($maxSectionVolume, ($maxVolume - $occupiedVolumeOverall));

                        $stockLeftToMove = $stockData['stock'] - $stockData['managedStock'];

                        $fillCount = floor($freeVolume / $articleVolume);
                        $fillCount = min($fillCount, $stockLeftToMove);

                        if ($stockPlaceConfig['multi_intelligence']) {
                            $intelligenceCount = $this->getArticleSales($article->article_number, $stockPlaceConfig['multi_intelligence_period']);
                            $intelligenceRefill = $intelligenceCount - $stockLeftToMove;

                            $fillCount = min($intelligenceRefill, $fillCount);
                        }

                        if ($fillCount != $stockLeftToMove) {
                            $fillCount = $this->roundQuantity($fillCount);
                        }

                        if ($fillCount <= 0) {
                            $toplistIndex++;
                            $failCount++;
                            $this->printLine('Fill count below 0.');
                            continue;
                        }

                        $this->makeStockMovement(
                            $article['article_number'],
                            0,
                            $compartment->id,
                            $fillCount,
                            self::MOVEMENT_TYPE_ORGANIZATION
                        );

                        break;
                    }
                }
            }
        }

        // Then process articles in classification order: A, B, C
        $this->printLine('Process articles in classification order');
        foreach (self::CLASSIFICATION_ORDER as $classIndex => $class) {
            $articles = $groupedArticles[$class] ?? [];

            foreach ($articles as $article) {
                if (!isset($articleStockData[$article->article_number])) {
                    $articleStockData[$article->article_number] = [
                        'stock' => $article->stock,
                        'managedStock' => 0,
                        'has_main_placement' => WarehouseHelper::articleHasPlacement($article->article_number, ['A', 'B']),
                    ];
                }

                $stockData = &$articleStockData[$article->article_number];

                $articleVolume = ($article->height / 1000) * ($article->width / 1000) * ($article->depth / 1000);

                // Iterate from 0 to current classIndex to include higher-priority stock places
                for ($i = 0;$i < $classIndex;$i++) {
                    $stockPlaceClass = self::CLASSIFICATION_ORDER[$i];
                    $stockPlaces = $groupedStockPlaces[$stockPlaceClass] ?? [];

                    $multiIntelligence = $this->config['wms_multi_intelligence_' . $stockPlaceClass];
                    $multiIntelligencePeriod = $this->config['wms_multi_intelligence_period_' . $stockPlaceClass];

                    if(!$stockPlaces) continue;

                    // Calculate already managed stock
                    foreach ($stockPlaces as $stockPlace) {
                        foreach ($stockPlace->compartments as $compartment) {
                            foreach ($compartment->stockItems as $stockItem) {
                                if ($stockItem->article_number != $article->article_number) continue;

                                $stockData['managedStock']++;
                            }
                        }
                    }


                    // Refill existing compartments with unmanaged stock items
                    foreach ($stockPlaces as $stockPlace) {
                        foreach ($stockPlace->compartments as $compartment) {
                            if ($compartment->is_manual || $compartment->is_reserved() || in_array($compartment->id, $unleashCompartmentIDs)) {
                                continue;
                            }

                            if (!$compartment->stockItems->count()) {
                                // Empty stock place, nothing to refill
                                continue;
                            }

                            $stockPlaceConfig = $this->getStockPlaceConfig($stockPlace, $compartment, $multiIntelligence, $multiIntelligencePeriod);

                            $uniqueStockItems = $compartment->stockItems->pluck('article_number')->unique();
                            $totalSections = $compartment->sections->count() ?: 1;

                            if ($uniqueStockItems->count() > $totalSections) {
                                // There are more items than expected in the compartments.
                                // Do not refill, instead wait for items to be moved away.
                                continue;
                            }

                            $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                            $maxVolume = $compartmentVolume * ($stockPlaceConfig['max_volume'] / 100);

                            foreach ($uniqueStockItems as $stockItemArticleNumber) {
                                if ($article->article_number != $stockItemArticleNumber) continue;

                                $stockItemCount = $compartment->stockItems->where('article_number', $stockItemArticleNumber)->count();

                                $sectionMaxVolume = $maxVolume / $uniqueStockItems->count();

                                $occupiedVolume = $articleVolume * $stockItemCount;
                                $freeVolume = $sectionMaxVolume - $occupiedVolume;

                                if ($freeVolume <= 0) {
                                    continue; // This compartment is already full
                                }

                                if (($occupiedVolume / $maxVolume) > self::REFILL_THRESHOLD) {
                                    continue; // No need to refill, volume is not below threshold
                                }

                                $stockLeftToMove = $stockData['stock'] - $stockData['managedStock'];

                                $maxArticles = floor($freeVolume / $articleVolume);
                                $refillCount = min($maxArticles, $stockLeftToMove);

                                if ($stockPlaceConfig['multi_intelligence'] && $stockPlaceClass == 'A') {
                                    $intelligenceCount = $this->getArticleSales($article->article_number, $stockPlaceConfig['multi_intelligence_period']);
                                    $intelligenceRefill = $intelligenceCount - $stockLeftToMove;

                                    $refillCount = min($intelligenceRefill, $refillCount);
                                }

                                if ($refillCount != $stockLeftToMove) {
                                    $refillCount = $this->roundQuantity($refillCount);
                                }

                                if (($refillCount + $this->config['empty_rest_products_' . $stockPlaceClass]) >= $stockLeftToMove) {
                                    $refillCount = $stockLeftToMove;
                                }

                                if ($refillCount <= 0) continue; // Not items found to refill

                                // Make a stock movement
                                $this->makeStockMovement(
                                    $article->article_number,
                                    0,
                                    $compartment->id,
                                    $refillCount,
                                    self::MOVEMENT_TYPE_REFILL
                                );

                                $stockData['managedStock'] += $refillCount;

                                if ($stockPlaceClass == 'A' || $stockPlaceClass == 'B') {
                                    $stockData['has_main_placement'] = true;
                                    continue 4; // Move to next article
                                }
                            }
                        }
                    }


                    // Allow only one placement in higher-priority stock places
                    if ($stockData['has_main_placement']
                        && ($stockPlaceClass == 'A' || $stockPlaceClass == 'B')) {
                        continue;
                    }


                    // Fill remaining unmanaged stock to new compartments
                    foreach ($stockPlaces as $stockPlace) {
                        foreach ($stockPlace->compartments as $compartment) {
                            if ($compartment->is_manual || $compartment->is_reserved() || in_array($compartment->id, $unleashCompartmentIDs)) {
                                continue;
                            }

                            if ($compartment->volume_class != $article->classification_volume) {
                                continue;
                            }

                            $stockPlaceConfig = $this->getStockPlaceConfig($stockPlace, $compartment, $multiIntelligence, $multiIntelligencePeriod);

                            $totalSections = $compartment->sections->count() ?: 1;

                            $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                            $maxVolume = $compartmentVolume * ($stockPlaceConfig['max_volume'] / 100);

                            $uniqueStockItems = $compartment->stockItems->pluck('article_number')->unique();
                            $uniqueArticleNumbers = $uniqueStockItems->values()->toArray();

                            $occupiedVolumeOverall = 0;

                            foreach ($compartment->stockItems as $stockItem) {
                                $stockItemVolume = ($stockItem->article->height / 1000) * ($stockItem->article->width / 1000) * ($stockItem->article->depth / 1000);
                                $occupiedVolumeOverall += $stockItemVolume;
                            }

                            if ($uniqueStockItems->count() >= $totalSections) {
                                continue; // This compartment already have full sections
                            }

                            for ($i = 0;$i < $totalSections;$i++) {
                                if (isset($uniqueArticleNumbers[$i])) {
                                    continue; // This section is already occupied
                                }

                                // Empty section, let's fill this one
                                if (count($this->movementCache[$compartment->id] ?? []) >= ($i + 1)) {
                                    // Another item is planed to move to this compartment
                                    continue;
                                }

                                $sectionVolume = $compartmentVolume / $totalSections;
                                $maxSectionVolume = $sectionVolume * ($stockPlaceConfig['max_volume'] / 100);

                                $freeVolume = min($maxSectionVolume, ($maxVolume - $occupiedVolumeOverall));

                                $stockLeftToMove = $stockData['stock'] - $stockData['managedStock'];

                                $fillCount = floor($freeVolume / $articleVolume);
                                $fillCount = min($fillCount, $stockLeftToMove);

                                if ($stockPlaceConfig['multi_intelligence'] && $stockPlaceClass == 'A') {
                                    $intelligenceCount = $this->getArticleSales($article->article_number, $stockPlaceConfig['multi_intelligence_period']);
                                    $intelligenceRefill = $intelligenceCount - $stockLeftToMove;

                                    $fillCount = min($intelligenceRefill, $fillCount);
                                }

                                if ($fillCount != $stockLeftToMove) {
                                    $fillCount = $this->roundQuantity($fillCount);
                                }

                                if ($fillCount <= 0) continue;

                                $this->makeStockMovement(
                                    $article->article_number,
                                    0,
                                    $compartment->id,
                                    $fillCount,
                                    self::MOVEMENT_TYPE_ORGANIZATION
                                );

                                $stockData['managedStock'] += $fillCount;
                                $occupiedVolumeOverall += ($articleVolume * $fillCount);

                                if ($stockPlaceClass == 'A' || $stockPlaceClass == 'B') {
                                    $stockData['has_main_placement'] = true;
                                    continue 4; // Move to next article
                                }
                            }
                        }
                    }
                }
            }
        }


        // Unleash marked compartments
        foreach ($unleashCompartmentIDs as $compartmentID) {
            $stockItems = StockItem::where('stock_place_compartment_id', $compartmentID)->get();

            if (!$stockItems) continue;

            $groupedStockItems = [];
            foreach ($stockItems as $stockItem) {
                $key = $stockItem->stock_place_compartment_id . '_' . $stockItem->article_number;
                if (!isset($groupedStockItems[$key])) {
                    $groupedStockItems[$key] = [
                        'article_number' => $stockItem->article_number,
                        'stock_place_compartment_id' => $stockItem->stock_place_compartment_id,
                        'quantity' => 0,
                    ];
                }

                $groupedStockItems[$key]['quantity']++;
            }

            foreach ($groupedStockItems as $data) {
                $this->makeStockMovement(
                    $data['article_number'],
                    $data['stock_place_compartment_id'],
                    0,
                    $data['quantity'],
                    self::MOVEMENT_TYPE_UNLEASH
                );
            }
        }

        // Unleash items for overfilled compartments
        foreach ($groupedStockPlaces as $stockPlaces) {
            foreach ($stockPlaces as $stockPlace) {
                foreach ($stockPlace->compartments as $compartment) {
                    if ($compartment->is_manual || $compartment->is_reserved() || in_array($compartment->id, $unleashCompartmentIDs)) {
                        continue;
                    }

                    $articleNumbers = $compartment->stockItems->pluck('article_number')->unique()->values()->toArray();
                    $totalSections = $compartment->sections->count() ?: 1;

                    if ($articleNumbers <= $totalSections) {
                        continue;
                    }

                    $articleNumbersToUnleash = array_slice($articleNumbers, $totalSections);

                    foreach ($articleNumbersToUnleash as $articleNumber) {
                        $quantity = StockItem::where('stock_place_compartment_id', $compartment->id)
                            ->where('article_number', $articleNumber)
                            ->count();

                        $this->makeStockMovement(
                            $articleNumber,
                            $compartment->id,
                            0,
                            $quantity,
                            self::MOVEMENT_TYPE_UNLEASH
                        );
                    }
                }
            }
        }


        ConfigController::setConfigs(['optimize_stock_running' => 0]);
        ConfigController::setConfigs(['optimize_stock_last_run' => date('Y-m-d H:i:s')]);

        return true;
    }

    private function getGroupedStockPlaces(): array
    {
        $stockPlaces = StockPlace::where('type', '=', 1)
            ->where('is_active', '=', 1)
            ->get();

        $groupedStockPlaces = [];

        foreach ($stockPlaces as $stockPlace) {
            $classification = $this->getClassificationByColor($stockPlace->color);

            if (!isset($groupedStockPlaces[$classification])) {
                $groupedStockPlaces[$classification] = [];
            }

            $groupedStockPlaces[$classification][] = $stockPlace;
        }

        return $groupedStockPlaces;
    }

    private function getArticlesToplist(): array
    {
        return DB::table('articles')
            ->select(['id', 'article_number', 'stock_manageable AS stock', 'classification', 'classification_volume', 'width', 'depth', 'height'])
            ->where('bestseller_position', '>', 0)
            ->where('width', '>', 0)
            ->where('height', '>', 0)
            ->where('depth', '>', 0)
            ->where('stock_manageable', '>', 0)
            ->orderBy('bestseller_position', 'ASC')
            ->get()
            ->toArray();
    }

    private function getGroupedArticles(): array
    {
        $articles = DB::table('articles')
            ->select(['id', 'article_number', 'stock_manageable AS stock', 'classification', 'classification_volume', 'width', 'depth', 'height'])
            ->where('width', '>', 0)
            ->where('height', '>', 0)
            ->where('depth', '>', 0)
            ->where('stock_manageable', '>', 0)
            ->get();

        $groupedArticles = [];

        foreach ($articles as $article) {
            if (!isset($groupedArticles[$article->classification])) {
                $groupedArticles[$article->classification] = [];
            }

            $groupedArticles[$article->classification][] = $article;
        }

        return $groupedArticles;
    }

    private function getClassificationByColor(string $color): ?string
    {
        return match (strtolower($color)) {
            '#50f25b' => 'A',
            '#f2a950' => 'B',
            '#f2505f' => 'C',
            default => null,
        };
    }

    public function makeStockMovement(string $articleNumber, int $fromStockPlaceCompartmentID, int $toStockPlaceCompartmentID, int $quantity, string $type): void
    {
        $this->printLine('Making stock movement for ' . $articleNumber . ' from ' . $fromStockPlaceCompartmentID . ' to ' . $toStockPlaceCompartmentID . ' (Pcs: ' . $quantity . ') (Type: ' . $type . ')');

        $stockItemMovement = StockItemMovement::create([
            'type' => $type,
            'article_number' => $articleNumber,
            'from_stock_place_compartment' => $fromStockPlaceCompartmentID,
            'to_stock_place_compartment' => $toStockPlaceCompartmentID,
            'quantity' => $quantity,
        ]);

        $this->addStockMovementToCache($stockItemMovement);
    }

    private function addStockMovementToCache(StockItemMovement $stockItemMovement)
    {
        if (!isset($this->movementCache[$stockItemMovement->to_stock_place_compartment])) {
            $this->movementCache[$stockItemMovement->to_stock_place_compartment] = [];
        }

        if (!isset($this->movementCache[$stockItemMovement->to_stock_place_compartment][$stockItemMovement->article_number])) {
            $this->movementCache[$stockItemMovement->to_stock_place_compartment][$stockItemMovement->article_number] = [
                'article_number' => $stockItemMovement->article_number,
                'quantity' => 0,
            ];
        }

        $this->movementCache[$stockItemMovement->to_stock_place_compartment][$stockItemMovement->article_number]['quantity'] += $stockItemMovement->quantity;
    }

    private function clearUnleashStatus($groupedStockPlaces)
    {
        $unleashCompartmentIDs = [];

        // Clear unleash status for empty stock places
        foreach ($groupedStockPlaces as $stockPlaces) {
            foreach ($stockPlaces as $stockPlace) {
                foreach ($stockPlace->compartments as $compartment) {
                    if (!$compartment->unleash) continue;

                    if ($compartment->stockItems->count()) {
                        $unleashCompartmentIDs[] = $compartment->id;
                    }
                    else {
                        $compartment->update(['unleash' => 0]);
                    }
                }
            }
        }

        return $unleashCompartmentIDs;
    }

    private function getArticleSales(string $articleNumber, int $days = 7)
    {
        $totalSalesQuantity = DB::table('customer_invoice_lines')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
            ->selectRaw('SUM(customer_invoice_lines.quantity) as total_quantity')
            ->where('customer_invoice_lines.article_number', '=', $articleNumber)
            ->where('customer_invoices.date', '>=', date('Y-m-d H:i:s', strtotime('-120 days')))
            ->pluck('total_quantity')
            ->first();

        $weeklySalesQuantity = $totalSalesQuantity / (120 / $days);

        return floor($weeklySalesQuantity * 1.1); // Add 10% margin
    }

    private function roundQuantity(int $quantity): int
    {
        return floor($quantity / 5) * 5;
    }

    private function getStockPlaceGroup(StockPlace $stockPlace): ?StockPlaceGroup
    {
        $stockPlaceGroup = StockPlaceGroup::whereJsonContains('stock_places', ((string) $stockPlace->id))->first();

        return $stockPlaceGroup ?: null;
    }

    private function getStockPlaceConfig(StockPlace $stockPlace, StockPlaceCompartment $compartment, $multiIntelligence, $multiIntelligencePeriod): array
    {
        $stockPlaceGroup = $this->getStockPlaceGroup($stockPlace);

        return [
            'max_volume' => (float) ($stockPlaceGroup->{'max_volume_class_' . $compartment->volume_class} ?? null) ?: $this->config['max_volume_' . $compartment->volume_class],
            'multi_intelligence' => (int) ($stockPlaceGroup->wms_multi_intelligence ?? null) ?: $multiIntelligence,
            'multi_intelligence_period' => (int) ($stockPlaceGroup->wms_multi_intelligence_period ?? null) ?: $multiIntelligencePeriod
        ];
    }

    private function printLine(string $string)
    {
        echo $string . PHP_EOL;
    }
}
