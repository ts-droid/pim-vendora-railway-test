<?php

namespace App\Services\WMS;

use App\Http\Controllers\ConfigController;
use App\Models\CompartmentSection;
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
                for ($i = 0;$i <= $classIndex;$i++) {
                    $stockPlaceClass = self::CLASSIFICATION_ORDER[$i];
                    $stockPlaces = $groupedStockPlaces[$stockPlaceClass] ?? [];

                    if (!$stockPlaces) continue;

                    // First look for existing placements
                    foreach ($stockPlaces as $stockPlace) {
                        foreach ($stockPlace->compartments as $compartment) {
                            foreach($compartment->stockItems as $stockItem) {
                                if ($stockItem->article_number != $article->article_number) continue;

                                $stockData['managedStock']++;
                            }
                        }
                    }

                    // Look for refills
                    foreach ($stockPlaces as $stockPlace) {
                        foreach ($stockPlace->compartments as $compartment) {
                            if ($compartment->is_manual || $compartment->is_reserved() || in_array($compartment->id, $unleashCompartmentIDs)) {
                                continue;
                            }

                            $stockItemCount = $compartment->stockItems->count();
                            if (!$stockItemCount) {
                                // Empty stock place
                                continue;
                            }

                            $stockPlaceGroup = $this->getStockPlaceGroup($stockPlace);

                            $stockPlaceConfig = [
                                'max_volume' => (float) ($stockPlaceGroup->{'max_volume_class_' . $compartment->volume_class} ?? null) ?: $this->config['max_volume_' . $compartment->volume_class],
                                'multi_intelligence' => (int) ($stockPlaceGroup->wms_multi_intelligence ?? null) ?: $multiIntelligence,
                                'multi_intelligence_period' => (int) ($stockPlaceGroup->wms_multi_intelligence_period ?? null) ?: $multiIntelligencePeriod
                            ];

                            $sectionIDs = $compartment->sections->pluck('id')->toArray();
                            $sectionIDs = $sectionIDs ?: [0];

                            foreach ($sectionIDs as $sectionID) {
                                $section = CompartmentSection::where('id', $sectionID)->first();

                                $stockItems = $section ? $section->stockItems : $compartment->stockItems;

                                if (!$stockItems->count()) {
                                    // Empty section
                                    continue;
                                }

                                $articleNumber = $stockItems->first()->article_number;
                                if ($articleNumber != $article->article_number) continue; // Another article is hosted at this place

                                // This article is hosted here, check if it's time to refill
                                $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                                $compartmentVolume = $compartmentVolume / count($sectionIDs);

                                $maxVolume = $compartmentVolume * ($stockPlaceConfig['max_volume'] / 100);
                                $maxArticles = floor($maxVolume / $articleVolume);

                                $occupiedVolume = $articleVolume * $stockItemCount;
                                $freeVolume = $maxVolume - $occupiedVolume;

                                // Refill the compartment
                                $stockLeftToMove = $stockData['stock'] - $stockData['managedStock'];

                                $refillCount = floor($freeVolume / $articleVolume);
                                $refillCount = min($refillCount, $stockLeftToMove);

                                if ($stockPlaceConfig['multi_intelligence']) {
                                    $maxArticlesToMove = min($stockLeftToMove, $maxArticles - $stockItemCount);

                                    $intelligenceCount = $this->getArticleSales($article->article_number, $stockPlaceConfig['multi_intelligence_period']);
                                    $intelligenceRefill = $intelligenceCount - $stockData['managedStock'];

                                    $refillCount = min($intelligenceRefill, $stockLeftToMove, $maxArticlesToMove);
                                }
                                else {
                                    if (($occupiedVolume / $maxVolume) > self::REFILL_THRESHOLD) {
                                        continue; // No need to refill, volume is not below threshold
                                    }
                                }

                                $refillCount = $this->roundQuantity($refillCount);

                                if ($refillCount <= 0) continue; // Not items found to refill with

                                // Make a stock movement
                                $this->makeStockMovement(
                                    $article->article_number,
                                    0,
                                    0,
                                    $compartment->id,
                                    $sectionID,
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
                    // Remove or adjust the following condition if necessary
                    if ($stockData['has_main_placement']
                        && ($stockPlaceClass == 'A' || $stockPlaceClass == 'B')) {
                        continue;
                    }

                    // Fill remaining stock to new compartments
                    foreach ($stockPlaces as $stockPlace) {
                        foreach ($stockPlace->compartments as $compartment) {
                            if ($compartment->is_manual || $compartment->is_reserved() || in_array($compartment->id, $unleashCompartmentIDs)) {
                                continue;
                            }

                            if ($compartment->volume_class != $article->classification_volume) {
                                continue;
                            }

                            $stockPlaceGroup = $this->getStockPlaceGroup($stockPlace);

                            $stockPlaceConfig = [
                                'max_volume' => (float) ($stockPlaceGroup->{'max_volume_class_' . $compartment->volume_class} ?? null) ?: $this->config['max_volume_' . $compartment->volume_class],
                                'multi_intelligence' => (int) ($stockPlaceGroup->wms_multi_intelligence ?? null) ?: $multiIntelligence,
                                'multi_intelligence_period' => (int) ($stockPlaceGroup->wms_multi_intelligence_period ?? null) ?: $multiIntelligencePeriod
                            ];

                            $sectionIDs = $compartment->sections->pluck('id')->toArray();
                            $sectionIDs = $sectionIDs ?: [0];

                            foreach ($sectionIDs as $sectionID) {
                                $section = CompartmentSection::where('id', $sectionID)->first();

                                $stockItems = $section ? $section->stockItems : $compartment->stockItems;

                                if ($stockItems->count()) {
                                    continue;
                                }

                                $compartmentCache = $this->movementCache[$compartment->id . '_' . $sectionID] ?? null;
                                if ($compartmentCache && $compartmentCache['article_number'] != $article->article_number) {
                                    // Another article is planed to move to this compartment
                                    continue;
                                }

                                $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                                $compartmentVolume = $compartmentVolume / count($sectionIDs);

                                $maxVolume = $compartmentVolume * ($stockPlaceConfig['max_volume'] / 100);
                                $maxArticles = floor($maxVolume / $articleVolume);

                                $occupiedVolume = $articleVolume * ($compartmentCache['quantity'] ?? 0);
                                $freeVolume = $maxVolume - $occupiedVolume;

                                $stockLeftToMove = $stockData['stock'] - $stockData['managedStock'];

                                $fillCount = floor($freeVolume / $articleVolume);
                                $fillCount = min($fillCount, $stockLeftToMove);

                                if ($stockPlaceConfig['multi_intelligence']) {
                                    $intelligenceCount = $this->getArticleSales($article->article_number, $stockPlaceConfig['multi_intelligence_period']);
                                    $freeCapacity = $maxArticles - ($compartmentCache['quantity'] ?? 0);
                                    $intelligenceFill = $intelligenceCount - $stockData['managedStock'];
                                    $fillCount = min($fillCount, $stockLeftToMove, $freeCapacity, $intelligenceFill);
                                }
                                else {
                                    $fillCount = min($fillCount, $stockLeftToMove);
                                }

                                $fillCount = min($fillCount, $maxArticles);
                                $fillCount = $this->roundQuantity($fillCount);

                                if (!$fillCount) continue;

                                // Make a stock movement
                                $this->makeStockMovement(
                                    $article->article_number,
                                    0,
                                    0,
                                    $compartment->id,
                                    $sectionID,
                                    $fillCount,
                                    'organization'
                                );

                                $stockData['managedStock'] += $fillCount;

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

        // Unleash items from stock places
        foreach ($unleashCompartmentIDs as $compartmentID) {
            $stockItems = StockItem::where('stock_place_compartment_id', $compartmentID)->get();

            if (!$stockItems) continue;

            $groupedStockItems = [];
            foreach ($stockItems as $stockItem) {
                $key = $stockItem->article_number . '_' . $stockItem->stock_place_compartment_id . '_' . $stockItem->compartment_section_id;
                if (!isset($groupedStockItems[$key])) {
                    $groupedStockItems[$key] = [
                        'article_number' => $stockItem->article_number,
                        'stock_place_compartment_id' => $stockItem->stock_place_compartment_id,
                        'compartment_section_id' => $stockItem->compartment_section_id,
                        'quantity' => 0,
                    ];
                }

                $groupedStockItems[$key]['quantity']++;
            }

            foreach ($groupedStockItems as $data) {
                $this->makeStockMovement(
                    $data['article_number'],
                    $data['stock_place_compartment_id'],
                    $data['compartment_section_id'],
                    0,
                    0,
                    $data['quantity'],
                    'unleash'
                );
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
            ->select(['id', 'article_number', 'stock_on_hand AS stock', 'classification', 'classification_volume', 'width', 'depth', 'height'])
            ->where('width', '>', 0)
            ->where('height', '>', 0)
            ->where('depth', '>', 0)
            ->where('stock_on_hand', '>', 0)
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

    private function makeStockMovement(string $articleNumber, int $fromStockPlaceCompartmentID, int $fromCompartmentSectionID, int $toStockPlaceCompartmentID, int $toCompartmentSectionID, int $quantity, string $type): void
    {
        $stockItemMovement = StockItemMovement::create([
            'type' => $type,
            'article_number' => $articleNumber,
            'from_stock_place_compartment' => $fromStockPlaceCompartmentID,
            'from_compartment_section' => $fromCompartmentSectionID,
            'to_stock_place_compartment' => $toStockPlaceCompartmentID,
            'to_compartment_section' => $toCompartmentSectionID,
            'quantity' => $quantity,
        ]);

        $this->addStockMovementToCache($stockItemMovement);
    }

    private function addStockMovementToCache(StockItemMovement $stockItemMovement)
    {
        $key = $stockItemMovement->to_stock_place_compartment . '_' . $stockItemMovement->to_compartment_section;

        if (!isset($this->movementCache[$key])) {
            $this->movementCache[$key] = [
                'article_number' => $stockItemMovement->article_number,
                'quantity' => 0,
            ];
        }

        $this->movementCache[$key]['quantity'] += $stockItemMovement->quantity;
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
}
