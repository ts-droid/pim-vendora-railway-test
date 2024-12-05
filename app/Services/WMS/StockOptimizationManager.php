<?php

namespace App\Services\WMS;

use App\Models\StockItemMovement;
use App\Models\StockPlace;
use Illuminate\Support\Facades\DB;

class StockOptimizationManager
{
    const CLASSIFICATION_ORDER = ['A', 'B', 'C'];

    const MAX_FILL = 0.8;
    const REFILL_THRESHOLD = 0.4;

    public function optimize(): void
    {
        // Remove all existing StockItemMovements
        StockItemMovement::truncate();

        $groupedStockPlaces = $this->getGroupedStockPlaces();
        $groupedArticles = $this->getGroupedArticles();

        for ($classIndex = 0;$classIndex < count(self::CLASSIFICATION_ORDER);$classIndex++) {
            $articles = $groupedArticles[self::CLASSIFICATION_ORDER[$classIndex]] ?? [];

            // Loop each article in this classification
            foreach ($articles as $article) {
                $totalStock = $article->stock;
                $managedStock = 0;

                $articleVolume = ($article->height / 1000) * ($article->width / 1000) * ($article->depth / 1000);

                // Loop until all stock for this article has been managed
                while($managedStock < $totalStock) {
                    // Start looking at the stock places with the same classification, and continue downwards
                    for ($i = $classIndex;$i < count(self::CLASSIFICATION_ORDER);$i++) {
                        $stockPlaces = $groupedStockPlaces[self::CLASSIFICATION_ORDER[$i]] ?? [];

                        if (!$stockPlaces) continue;

                        // First look for existing placements
                        foreach ($stockPlaces as $stockPlace) {
                            foreach ($stockPlace->compartments as $compartment) {
                                foreach($compartment->stockItems as $stockItem) {
                                    if ($stockItem->article_number != $article->article_number) continue;

                                    $managedStock++;
                                }
                            }
                        }

                        // Look for refills
                        foreach ($stockPlaces as $stockPlace) {
                            foreach ($stockPlace->compartments as $compartment) {
                                $stockItemCount = $compartment->stockItems->count();

                                if (!$stockItemCount) continue; // Empty stock place

                                $articleNumber = $compartment->stockItems->first()->article_number;
                                if ($articleNumber != $article->article_number) continue; // Another article is hosted in this compartment

                                // This article is hosted here, check if it is time to refill
                                $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                                $occupiedVolume = $articleVolume * $stockItemCount;
                                $freeVolume = ($compartmentVolume * self::MAX_FILL) - $occupiedVolume;

                                if ($occupiedVolume > self::REFILL_THRESHOLD) continue; // No need to refill, volume is not below 40%

                                // Refill the compartment
                                $refillCount = floor($freeVolume / $articleVolume);
                                $refillCount = min($refillCount, ($totalStock - $managedStock));

                                if (!$refillCount) continue; // Not items found to refill with

                                // Make a stock movement
                                StockItemMovement::create([
                                    'article_number' => $article->article_number,
                                    'from_stock_place_compartment' => 0,
                                    'to_stock_place_compartment' => $compartment->id,
                                    'quantity' => $refillCount,
                                ]);

                                $managedStock += $refillCount;
                            }
                        }

                        // Fill remaining stock to new compartments
                        foreach ($stockPlaces as $stockPlace) {
                            foreach ($stockPlace->compartments as $compartment) {
                                if ($compartment->stockItems->count()) continue; // Compartment is not empty

                                $compartmentVolume = ($compartment->height / 100) * ($compartment->width / 100) * ($compartment->depth / 100);
                                $freeVolume = $compartmentVolume * self::MAX_FILL;

                                $fillCount = floor($freeVolume / $articleVolume);
                                $fillCount = min($fillCount, ($totalStock - $managedStock));

                                if (!$fillCount) continue;

                                // Make a stock movement
                                StockItemMovement::create([
                                    'article_number' => $article->article_number,
                                    'from_stock_place_compartment' => 0,
                                    'to_stock_place_compartment' => $compartment->id,
                                    'quantity' => $fillCount,
                                ]);

                                $managedStock += $fillCount;
                            }
                        }
                    }
                }
            }
        }
    }

    private function getGroupedStockPlaces(): array
    {
        $stockPlaces = StockPlace::where('type', '=', 1)->get();

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
            ->select(['id', 'article_number', 'stock_on_hand AS stock', 'classification', 'width', 'depth', 'height'])
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
}
