<?php

namespace App\Services\WMS;

use App\Http\Controllers\ConfigController;
use App\Models\StockItem;
use App\Models\StockItemMovement;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Models\StockPlaceGroup;
use App\Utilities\WarehouseHelper;
use Illuminate\Support\Facades\DB;

class StockOptimizationManager
{
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
            'wms_multi_intelligence' => ConfigController::getConfig('wms_multi_intelligence', 0),
            'wms_multi_intelligence_period' => ConfigController::getConfig('wms_multi_intelligence_period', 7)
        ];
    }

    public function optimize(): bool
    {
        ConfigController::setConfigs(['optimize_stock_running' => 1]);

        $lastWorkTime = StockItemMovement::all()->max('ping_at');
        if ($lastWorkTime > (time() - 60)) {
            // Do not run the operation if someone is working on a stock movement
            ConfigController::setConfigs(['optimize_stock_running' => 0]);
            return false;
        }

        // Remove all existing StockItemMovements
        DB::table('stock_item_movements')->truncate();

        // Add existing stock movements to the cache
        $existingMovements = StockItemMovement::all();
        foreach ($existingMovements as $stockItemMovement) {
            $this->addStockMovementToCache($stockItemMovement);
        }

        $groupedStockPlaces = $this->getGroupedStockPlaces();
        $groupedArticles = $this->getGroupedArticles();

        $unleashCompartmentIDs = $this->clearUnleashStatus($groupedStockPlaces);

        $multiIntelligence = $this->config['wms_multi_intelligence'];
        $multiIntelligencePeriod = $this->config['wms_multi_intelligence_period'];

        $articleStockData = [];

        // Process articles in classification order: A, B, C
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

                                $refillCount = $this->roundQuantity($refillCount);

                                if ($refillCount <= 0) continue; // Not items found to refill

                                // Make a stock movement
                                $this->makeStockMovement(
                                    $article->article_number,
                                    0,
                                    $compartment->id,
                                    $refillCount,
                                    'refill'
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

                                $fillCount = $this->roundQuantity($fillCount);

                                if ($fillCount <= 0) continue;

                                $this->makeStockMovement(
                                    $article->article_number,
                                    0,
                                    $compartment->id,
                                    $fillCount,
                                    'organization'
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
                    'unleash'
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
                            'unleash'
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

    private function makeStockMovement(string $articleNumber, int $fromStockPlaceCompartmentID, int $toStockPlaceCompartmentID, int $quantity, string $type): void
    {
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
}
