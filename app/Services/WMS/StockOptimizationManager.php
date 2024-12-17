<?php

namespace App\Services\WMS;

use App\Http\Controllers\ConfigController;
use App\Models\StockItem;
use App\Models\StockItemMovement;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use Illuminate\Support\Facades\DB;

class StockOptimizationManager
{
    const CLASSIFICATION_ORDER = ['A', 'B', 'C'];

    const MAX_FILL = 0.7;               // Fill compartments to max 70% of its volume
    const REFILL_THRESHOLD = 0.5;       // Refill a compartment when occupied volume is below 50%

    private array $config;

    private $movementCache = [];

    public function __construct()
    {
        $this->config = [
            'max_quantity_A' => ConfigController::getConfig('max_quantity_class_size_a', 100),
            'max_quantity_B' => ConfigController::getConfig('max_quantity_class_size_b', 100),
            'max_quantity_C' => ConfigController::getConfig('max_quantity_class_size_c', 100),
        ];
    }

    public function optimize(): void
    {
        $lastWorkTime = StockItemMovement::all()->max('ping_at');
        if ($lastWorkTime > (time() - 60)) {
            // Do not run the operation if someone is working on a stock movement
            return;
        }

        // Remove all existing StockItemMovements
        StockItemMovement::truncate();

        // Add existing stock movements to the cache
        $existingMovements = StockItemMovement::all();
        foreach ($existingMovements as $stockItemMovement) {
            $this->addStockMovementToCache($stockItemMovement);
        }

        $groupedStockPlaces = $this->getGroupedStockPlaces();
        $groupedArticles = $this->getGroupedArticles();

        $unleashCompartmentIDs = $this->clearUnleashStatus($groupedStockPlaces);

        $articleStockData = [];

        // Process articles in classification order: A, B, C
        foreach (self::CLASSIFICATION_ORDER as $classIndex => $class) {
            $articles = $groupedArticles[$class] ?? [];

            foreach ($articles as $article) {
                if (!isset($articleStockData[$article->article_number])) {
                    $articleStockData[$article->article_number] = [
                        'stock' => $article->stock,
                        'managedStock' => 0,
                        'has_main_placement' => false,
                    ];
                }

                $stockData = &$articleStockData[$article->article_number];

                $articleVolume = ($article->height / 1000) * ($article->width / 1000) * ($article->depth / 1000);

                // Iterate from 0 to current classIndex to include higher-priority stock places
                for ($i = 0;$i <= $classIndex;$i++) {
                    $stockPlaceClass = self::CLASSIFICATION_ORDER[$i];
                    $stockPlaces = $groupedStockPlaces[$stockPlaceClass] ?? [];

                    if (!$stockPlaces) continue;

                    // Allow multiple placements in higher-priority stock places
                    // Remove or adjust the following condition if necessary
                    if ($stockData['has_main_placement']
                        && ($stockPlaceClass == 'A' || $stockPlaceClass == 'B')) {
                        continue;
                    }

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

                            if (!$stockItemCount) continue; // Empty stock place

                            $articleNumber = $compartment->stockItems->first()->article_number;
                            if ($articleNumber != $article->article_number) continue; // Another article is hosted in this compartment

                            // This article is hosted here, check if it is time to refill
                            $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                            $maxVolume = min(($compartmentVolume * self::MAX_FILL), ($articleVolume * $this->config['max_quantity_' . $stockPlaceClass]));

                            $occupiedVolume = $articleVolume * $stockItemCount;
                            $freeVolume = $maxVolume - $occupiedVolume;

                            if ($occupiedVolume > self::REFILL_THRESHOLD) continue; // No need to refill, volume is not below 40%

                            // Refill the compartment
                            $refillCount = floor($freeVolume / $articleVolume);
                            $refillCount = min($refillCount, ($stockData['stock'] - $stockData['managedStock']));
                            $refillCount = $this->roundQuantity($refillCount);

                            if (!$refillCount) continue; // Not items found to refill with

                            // Make a stock movement
                            $this->makeStockMovement(
                                $article->article_number,
                                0,
                                $compartment->id,
                                $refillCount
                            );

                            $stockData['managedStock'] += $refillCount;

                            if ($stockPlaceClass == 'A' || $stockPlaceClass == 'B') {
                                $stockData['has_main_placement'] = true;
                                continue 3; // Move to next article
                            }
                        }
                    }

                    // Fill remaining stock to new compartments
                    foreach ($stockPlaces as $stockPlace) {
                        foreach ($stockPlace->compartments as $compartment) {
                            if ($compartment->is_manual || $compartment->is_reserved() || in_array($compartment->id, $unleashCompartmentIDs)) {
                                continue;
                            }

                            if ($compartment->stockItems->count()) {
                                continue;
                            }

                            if ($compartment->volume_class != $article->classification_volume) {
                                continue;
                            }

                            $compartmentCache = $this->movementCache[$compartment->id] ?? null;
                            if ($compartmentCache && $compartmentCache['article_number'] != $article->article_number) {
                                // Another article is planed to moved to this compartment
                                continue;
                            }

                            $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                            $maxVolume = min(($compartmentVolume * self::MAX_FILL), ($articleVolume * $this->config['max_quantity_' . $stockPlaceClass]));

                            $occupiedVolume = $articleVolume * ($compartmentCache['quantity'] ?? 0);
                            $freeVolume = $maxVolume - $occupiedVolume;

                            $fillCount = floor($freeVolume / $articleVolume);
                            $fillCount = min($fillCount, ($stockData['stock'] - $stockData['managedStock']));
                            $fillCount = $this->roundQuantity($fillCount);

                            if (!$fillCount) continue;

                            // Make a stock movement
                            $this->makeStockMovement(
                                $article->article_number,
                                0,
                                $compartment->id,
                                $fillCount
                            );

                            $stockData['managedStock'] += $fillCount;

                            if ($stockPlaceClass == 'A' || $stockPlaceClass == 'B') {
                                $stockData['has_main_placement'] = true;
                                continue 3; // Move to next article
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
                if (!isset($groupedStockItems[$stockItem->article_number])) {
                    $groupedStockItems[$stockItem->article_number] = 0;
                }

                $groupedStockItems[$stockItem->article_number]++;
            }

            foreach ($groupedStockItems as $articleNumber => $quantity) {
                $this->makeStockMovement(
                    $articleNumber,
                    $compartmentID,
                    0,
                    $quantity
                );
            }
        }
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

    private function makeStockMovement(string $articleNumber, int $fromStockPlaceCompartmentID, int $toStockPlaceCompartmentID, int $quantity): void
    {
        $stockItemMovement = StockItemMovement::create([
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
            $this->movementCache[$stockItemMovement->to_stock_place_compartment] = [
                'article_number' => $stockItemMovement->article_number,
                'quantity' => 0,
            ];
        }

        $this->movementCache[$stockItemMovement->to_stock_place_compartment]['quantity'] += $stockItemMovement->quantity;
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

    private function roundQuantity(int $quantity): int
    {
        return floor($quantity / 5) * 5;
    }

    private function debug(string $string)
    {
        echo $string . PHP_EOL;
    }
}
