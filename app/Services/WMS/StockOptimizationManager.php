<?php

namespace App\Services\WMS;

use App\Http\Controllers\ConfigController;
use App\Models\StockItem;
use App\Models\StockItemMovement;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Models\StockPlaceGroup;
use App\Models\TodoItem;
use App\Utilities\WarehouseHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class StockOptimizationManager
{
    const MOVEMENT_TYPE_REFILL = 'refill';
    const MOVEMENT_TYPE_ORGANIZATION = 'organization';
    const MOVEMENT_TYPE_UNLEASH = 'unleash';

    private array $config;

    private array $movementCache = [];
    private array $stockData = [];

    private $stockPlaces;
    private $articles;
    private $groupedArticles;

    public function __construct()
    {
        $this->config = [
            'wms_max_fill_size' => (int)ConfigController::getConfig('wms_max_fill_size', 100),
            'wms_min_fill_size' => (int)ConfigController::getConfig('max_volume_class_size_b', 0),
            'wms_sales_history_period' => (int)ConfigController::getConfig('wms_sales_history_period', 30),
        ];

        $this->stockPlaces = $this->getStockPlaces();
        $this->articles = $this->getArticles();
        $this->groupedArticles = $this->getGroupedArticles();
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
        Artisan::call('articles:classify', ['type' => 'wms']);

        // Remove all existing StockItemMovements
        $this->printLine('Removing existing stock movements.');
        DB::table('stock_item_movements')
            ->where('is_persistent', 0)
            ->delete();

        // Add existing stock movements to cache
        $stockMovements = StockItemMovement::all();
        if ($stockMovements) {
            foreach ($stockMovements as $stockMovement) {
                $this->addStockMovementToCache($stockMovement);
            }
        }

        $unleashCompartmentIDs = $this->clearUnleashStatus($this->stockPlaces);

        // Try to fill stock places from unmanaged stock
        foreach ($this->stockPlaces as $stockPlace) {

            $stockPlaceConfig = $this->getStockPlaceConfig($stockPlace);

            foreach ($stockPlace->compartments as $compartment) {
                if ($compartment->is_manual || $compartment->is_reserved() || in_array($compartment->id, $unleashCompartmentIDs)) {
                    continue;
                }

                $toplistIndex = 0;

                $articles = $this->getArticleGroup($compartment->volume_class);

                $identifier = $stockPlace->identifier . ':' . $compartment->identifier;
                $this->printLine('Processing ' . $identifier);

                $uniqueStockItems = $compartment->stockItems->pluck('article_number')->unique();
                $totalSections = $compartment->sections->count() ?: 1;

                if ($uniqueStockItems->count() >= $totalSections) {
                    // No empty compartments
                    $this->printLine('No empty compartments.');
                    continue;
                }

                $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                $maxVolume = $compartmentVolume * ($stockPlaceConfig['max_volume'] / 100);

                $sectionVolume = $compartmentVolume / $totalSections;
                $maxSectionVolume = $sectionVolume * ($stockPlaceConfig['max_volume'] / 100);

                $occupiedVolumeOverall = $this->getOccupiedVolume($compartment);

                $emptySections = $totalSections - $uniqueStockItems->count();

                for ($i = 0; $i < $emptySections; $i++) {
                    $failCount = 0;

                    $this->printLine('Processing compartment index ' . $i);

                    while ($failCount < 1000) {
                        $article = $articles[$toplistIndex] ?? null;
                        if (!$article) {
                            $this->printLine('No more articles found to fill.');
                            break 3; // No more articles to fill
                        }

                        $this->printLine('Selecting article: ' . $article->article_number);

                        $this->initStockData($article);
                        $stockData = &$this->stockData[$article->article_number];

                        if ($stockData['has_main_placement']) {
                            $toplistIndex++;
                            $failCount++;
                            $this->printLine('Article already have placement in main compartment');
                            continue;
                        }

                        $articleVolume = ($article->height / 1000) * ($article->width / 1000) * ($article->depth / 1000);

                        $freeVolume = min($maxSectionVolume, ($maxVolume - $occupiedVolumeOverall));

                        // Fill using unmanaged stock if possible
                        $unmanagedStock = $stockData['stock'] - $stockData['managed_stock'];

                        if ($unmanagedStock > 0) {
                            // Fill using unmanaged stock
                            $stockLeftToMove = max(0, $unmanagedStock);

                            $fillCount = floor($freeVolume / $articleVolume);
                            $fillCount = min($fillCount, $stockLeftToMove);

                            if ($fillCount > 0) {
                                $this->makeStockMovement(
                                    $article->article_number,
                                    0,
                                    $compartment->id,
                                    $fillCount,
                                    self::MOVEMENT_TYPE_ORGANIZATION
                                );

                                $stockData['managed_stock'] += $fillCount;
                                $stockData['has_main_placement'] = true;

                                $occupiedVolumeOverall += ($articleVolume * $fillCount);

                                $toplistIndex++;

                                break;
                            }
                        }

                        // Try to fill using managed stock instead
                        $locations = WarehouseHelper::getArticleLocationsWithStock($article->article_number);
                        foreach ($locations as $location) {
                            if ($location['identifier'] == '--') {
                                continue;
                            }

                            $stockLeftToMove = max(0, $location['stock']);

                            $fillCount = floor($freeVolume / $articleVolume);
                            $fillCount = min($fillCount, $stockLeftToMove);

                            if ($fillCount > 0) {
                                $lookup = WarehouseHelper::getStockPlaceAndCompartment($location['identifier']);

                                $fromStockPlaceCompartment = $lookup['stock_place_compartment'] ?? null;

                                if ($fromStockPlaceCompartment) {
                                    $this->makeStockMovement(
                                        $article->article_number,
                                        $fromStockPlaceCompartment->id,
                                        $compartment->id,
                                        $fillCount,
                                        self::MOVEMENT_TYPE_ORGANIZATION
                                    );

                                    $stockData['has_main_placement'] = true;

                                    $occupiedVolumeOverall += ($articleVolume * $fillCount);

                                    $toplistIndex++;

                                    break 2;
                                }
                            }
                        }

                        $toplistIndex++;
                    }
                }
            }
        }

        // Try to refill stock places from unmanaged stock
        foreach ($this->stockPlaces as $stockPlace) {

            $stockPlaceConfig = $this->getStockPlaceConfig($stockPlace);

            foreach ($stockPlace->compartments as $compartment) {
                if ($compartment->is_manual || $compartment->is_reserved() || in_array($compartment->id, $unleashCompartmentIDs)) {
                    continue;
                }

                if (!$compartment->stockItems->count()) {
                    // Empty stock place, nothing to refill
                    continue;
                }

                $uniqueStockItems = $compartment->stockItems->pluck('article_number')->unique();
                $totalSections = $compartment->sections->count() ?: 1;

                if ($uniqueStockItems->count() > $totalSections) {
                    // There are more items than expected in the compartments.
                    // Do not refill, instead wait for items to be moved away.
                    continue;
                }

                $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                $maxVolume = $compartmentVolume * ($stockPlaceConfig['max_volume'] / 100);

                $totalSections = $compartment->sections->count() ?: 1;
                $sectionMaxVolume = $maxVolume / $totalSections;

                $refillThreshold = $sectionMaxVolume * ($stockPlaceConfig['min_volume'] / 100);

                // Try to refill from unmanaged stock
                foreach ($uniqueStockItems as $stockItemArticleNumber) {

                    $article = $this->getArticle($stockItemArticleNumber);
                    if (!$article) {
                        continue;
                    }

                    $articleVolume = ($article->height / 1000) * ($article->width / 1000) * ($article->depth / 1000);

                    $occupiedVolume = $this->getOccupiedVolume($compartment, $stockItemArticleNumber);
                    $freeVolume = $sectionMaxVolume - $occupiedVolume;

                    if ($occupiedVolume > $refillThreshold) {
                        // Not below threshold to refill
                        continue;
                    }

                    $this->initStockData($article);
                    $stockData = &$this->stockData[$article->article_number];

                    $stockLeftToMove = $stockData['stock'] - $stockData['managed_stock'];

                    $maxArticles = floor($freeVolume / $articleVolume);
                    $refillCount = min($maxArticles, $stockLeftToMove);

                    if ($refillCount <= 0) continue; // Not items found to refill

                    $this->makeStockMovement(
                        $article->article_number,
                        0,
                        $compartment->id,
                        $refillCount,
                        self::MOVEMENT_TYPE_REFILL
                    );

                    $stockData['managed_stock'] += $refillCount;
                    $stockData['has_main_placement'] = true;
                }

                // Try to refill from managed stock
                foreach ($uniqueStockItems as $stockItemArticleNumber) {

                    $article = $this->getArticle($stockItemArticleNumber);

                    if (!$article) {
                        continue;
                    }

                    $articleVolume = ($article->height / 1000) * ($article->width / 1000) * ($article->depth / 1000);

                    $occupiedVolume = $this->getOccupiedVolume($compartment, $stockItemArticleNumber);

                    if ($occupiedVolume > $refillThreshold) {
                        // Not below threshold to refill
                        continue;
                    }

                    $this->initStockData($article);
                    $stockData = &$this->stockData[$article->article_number];

                    $stockLocations = WarehouseHelper::getArticleLocationsWithStock($article->article_number);

                    foreach ($stockLocations as $stockLocation) {
                        if ($stockLocation['identifier'] == '--' || $stockLocation['stock'] == 0) {
                            continue;
                        }

                        $freeVolume = $sectionMaxVolume - $occupiedVolume;

                        $maxArticles = floor($freeVolume / $articleVolume);
                        $refillCount = min($maxArticles, $stockLocation['stock']);

                        if ($refillCount >= 0) continue;

                        $response = WarehouseHelper::getStockPlaceAndCompartment($stockLocation['identifier']);
                        $fromCompartment = $response['stock_place_compartment'] ?? null;

                        if (!$fromCompartment) continue;

                        $this->makeStockMovement(
                            $article->article_number,
                            $fromCompartment->id,
                            $compartment->id,
                            $refillCount,
                            self::MOVEMENT_TYPE_REFILL
                        );

                        $stockData['managed_stock'] += $refillCount;
                        $stockData['has_main_placement'] = true;

                        $occupiedVolume += ($articleVolume * $refillCount);
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
        foreach ($this->stockPlaces as $stockPlace) {
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

        ConfigController::setConfigs(['optimize_stock_running' => 0]);
        ConfigController::setConfigs(['optimize_stock_last_run' => date('Y-m-d H:i:s')]);

        return true;
    }

    private function getArticle(string $articleNumber)
    {
        foreach ($this->articles as $article) {
            if ($article->article_number == $articleNumber) {
                return $article;
            }
        }

        return null;
    }

    private function initStockData($article)
    {
        if (!isset($this->stockData[$article->article_number])) {

            $reservedStock = WarehouseHelper::getReservedStock($article->article_number);

            $locations = WarehouseHelper::getArticleLocationsWithStock($article->article_number);

            $managedStock = 0;
            foreach ($locations as $location) {
                if ($location['identifier'] == '--') {
                    continue;
                }

                $managedStock += $location['stock'];
            }

            $this->stockData[$article->article_number] = [
                'stock' => $article->stock - $reservedStock,
                'managed_stock' => $managedStock,
                'has_main_placement' => WarehouseHelper::articleHasPlacement($article->article_number, ['A']),
            ];
        }
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

    private function addStockMovementToCache(StockItemMovement $stockItemMovement): void
    {
        if (!isset($this->movementCache[$stockItemMovement->to_stock_place_compartment])) {
            $this->movementCache[$stockItemMovement->to_stock_place_compartment] = [];
        }

        if (!isset($this->movementCache[$stockItemMovement->to_stock_place_compartment][$stockItemMovement->article_number])) {
            $this->movementCache[$stockItemMovement->to_stock_place_compartment][$stockItemMovement->article_number] = [
                'article_number' => $stockItemMovement->article_number,
                'width' => $stockItemMovement->article->width,
                'height' => $stockItemMovement->article->height,
                'depth' => $stockItemMovement->article->depth,
                'quantity' => 0,
            ];
        }

        $this->movementCache[$stockItemMovement->to_stock_place_compartment][$stockItemMovement->article_number]['quantity'] += $stockItemMovement->quantity;
    }

    private function getOccupiedVolume(StockPlaceCompartment $compartment, string $articleNumber = null): float
    {
        $occupiedVolume = 0;

        // Add stock items
        foreach ($compartment->stockItems as $stockItem) {
            if ($articleNumber && $articleNumber != $stockItem->article_number) {
                continue;
            }

            $stockItemVolume = ($stockItem->article->height / 1000) * ($stockItem->article->width / 1000) * ($stockItem->article->depth / 1000);
            $occupiedVolume += $stockItemVolume;
        }

        // Add stock movements
        $movements = $this->movementCache[$compartment->id] ?? [];
        foreach ($movements as $movement) {
            if ($articleNumber && $articleNumber != $movement['article_number']) {
                continue;
            }

            $articleVolume = ($movement['height'] / 1000) * ($movement['width'] / 1000) * ($movement['depth'] / 1000);
            $occupiedVolume += $articleVolume * $movement['quantity'];
        }

        return $occupiedVolume;
    }

    private function clearUnleashStatus($stockPlaces): array
    {
        $unleashCompartmentIDs = [];

        foreach ($stockPlaces as $stockPlace) {
            foreach ($stockPlace->compartments as $compartment) {
                if (!$compartment->unleash) continue;

                if ($compartment->stockitems->count()) {
                    $unleashCompartmentIDs[] = $compartment->id;
                }
                else {
                    $compartment->update(['unleash' => 0]);
                }
            }
        }

        return $unleashCompartmentIDs;
    }

    private function getStockPlaces()
    {
        return StockPlace::where('type', '=', 1)
            ->where('is_active', '=', 1)
            ->where('color', '=', WarehouseHelper::classToColor('A'))
            ->get();
    }

    private function getArticleGroup(string $group)
    {
        if ($group === 'A') {
            $groups = ['A', 'B', 'C'];
        }
        else if ($group === 'B') {
            $groups = ['B', 'A', 'C'];
        }
        else if ($group === 'C') {
            $groups = ['C', 'B', 'A'];
        }

        $articles = [];

        foreach ($groups as $group) {
            $articles = array_merge($articles, $this->groupedArticles[$group]);
        }

        return array_values($articles);
    }

    private function getGroupedArticles()
    {
        $groupedArticles = [
            'A' => [],
            'B' => [],
            'C' => [],
        ];

        foreach ($this->articles as $article) {
            $groupedArticles[$article->classification_volume][] = $article;
        }

        return $groupedArticles;
    }

    private function getArticles()
    {
        // Get articles with an active todo item
        $todos = TodoItem::where('on_hold', 0)
            ->whereNull('reserved_at')
            ->whereNull('completed_at')
            ->orderBy('list_order', 'ASC')
            ->get();

        $todoArticleIDs = [];
        foreach ($todos as $todo) {
            $todoArticleIDs[] = $todo->data['article_id'];
        }

        return DB::table('articles')
            ->select(['id', 'article_number', 'stock_manageable AS stock', 'width', 'depth', 'height', 'classification_volume'])
            ->where('wms_toplist', '>', 0)
            ->where('width', '>', 0)
            ->where('height', '>', 0)
            ->where('depth', '>', 0)
            ->where('stock_manageable', '>', 0)
            ->whereNotIn('id', $todoArticleIDs)
            ->orderBy('wms_toplist', 'ASC')
            ->get();
    }

    private function getStockPlaceConfig(StockPlace $stockPlace)
    {
        $stockPlaceGroup = StockPlaceGroup::whereJsonContains('stock_places', ((string) $stockPlace->id))->first();

        $groupMaxVolume = $stockPlaceGroup->max_volume ?? 0;
        $groupMinVolume = $stockPlaceGroup->min_volume ?? 0;

        return [
            'max_volume' => intval($groupMaxVolume ?: $this->config['wms_max_fill_size']),
            'min_volume' => intval($groupMinVolume ?: $this->config['wms_min_fill_size']),
        ];
    }

    private function printLine(string $string)
    {
        echo $string . PHP_EOL;
    }
}
















class StockOptimizationManagerOLD
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

        foreach (($groupedStockPlaces['A'] ?? []) as $stockPlace) {
            foreach ($stockPlace->compartments as $compartment) {
                if ($compartment->is_manual || $compartment->is_reserved() || in_array($compartment->id, $unleashCompartmentIDs)) {
                    continue;
                }

                $toplistIndex = 0;

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

                        $this->printLine('Selecting article: ' . $article->article_number);

                        if (!isset($articleStockData[$article->article_number])) {
                            $articleStockData[$article->article_number] = [
                                'stock' => $article->stock,
                                'managedStock' => 0,
                                'has_a_placement' => WarehouseHelper::articleHasPlacement($article->article_number, ['A']),
                                'has_main_placement' => WarehouseHelper::articleHasPlacement($article->article_number, ['A', 'B']),
                            ];
                        }

                        $stockData = &$articleStockData[$article->article_number];

                        if ($stockData['has_a_placement']) {
                            $toplistIndex++;
                            $failCount++;
                            $this->printLine('Article already have placement in an A compartment');
                            continue;
                        }

                        $articleVolume = ($article->height / 1000) * ($article->width / 1000) * ($article->depth / 1000);

                        $freeVolume = min($maxSectionVolume, ($maxVolume - $occupiedVolumeOverall));

                        $stockLeftToMove = $stockData['stock'] - $stockData['managedStock'];

                        $fillCount = floor($freeVolume / $articleVolume);
                        $fillCount = min($fillCount, $stockLeftToMove);

                        if ($stockPlaceConfig['multi_intelligence'] && $fillCount > 0) {
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
                            $article->article_number,
                            0,
                            $compartment->id,
                            $fillCount,
                            self::MOVEMENT_TYPE_ORGANIZATION
                        );

                        $stockData['managedStock'] += $fillCount;
                        $stockData['has_main_placement'] = true;
                        $stockData['has_a_placement'] = true;

                        $occupiedVolumeOverall += ($articleVolume * $fillCount);

                        $toplistIndex++;

                        break;
                    }
                }
            }
        }

        // Then process articles in classification order: A, B, C
        /*$this->printLine('Process articles in classification order');
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
                            $uniqueStockItemsCount = $uniqueStockItems->count();
                            $uniqueArticleNumbers = $uniqueStockItems->values()->toArray();

                            $cacheItems = $this->movementCache[$compartment->id] ?? [];
                            foreach ($cacheItems as $cacheItem) {
                                $articleNumber = $cacheItem['article_number'];

                                if (!in_array($articleNumber, $uniqueArticleNumbers)) {
                                    $uniqueArticleNumbers[] = $articleNumber;
                                    $uniqueStockItemsCount++;
                                }
                            }

                            $occupiedVolumeOverall = 0;

                            foreach ($compartment->stockItems as $stockItem) {
                                $stockItemVolume = ($stockItem->article->height / 1000) * ($stockItem->article->width / 1000) * ($stockItem->article->depth / 1000);
                                $occupiedVolumeOverall += $stockItemVolume;
                            }

                            if ($uniqueStockItemsCount >= $totalSections) {
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
        }*/


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
            ->where('color', '=', WarehouseHelper::classToColor('A'))
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
