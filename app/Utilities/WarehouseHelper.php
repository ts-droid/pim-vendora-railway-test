<?php

namespace App\Utilities;

use App\Models\StockItem;
use App\Models\StockPlace;

class WarehouseHelper
{
    public static function getArticleLocations(string $articleNumber, int $quantity): array
    {
        $colors = [
            '#50f25b', // A
            '#f2a950', // B
            '#f2505f', // C
        ];

        $stockPlaces = StockPlace::whereIn('color', $colors)
            ->orderByRaw("FIELD(color, ?, ?, ?)", $colors)
            ->where('is_active', 1)
            ->get();

        // Check if any compartment can deliver the entire quantity
        foreach($stockPlaces as $stockPlace) {
            foreach($stockPlace->compartments as $compartment) {
                $sectionIDs = array_merge([0], $compartment->sections->pluck('id')->toArray());

                foreach ($sectionIDs as $sectionID) {
                    $stockItemsCount = StockItem::where('article_number', $articleNumber)
                        ->where('stock_place_compartment_id', $compartment->id)
                        ->where('compartment_section_id', $sectionID)
                        ->count();

                    if ($stockItemsCount >= $quantity) {
                        return [$stockPlace->identifier . ':' . $compartment->identifier];
                    }
                }
            }
        }

        // Look recursively through all compartments
        $count = 0;
        $pickingPlaces = [];

        foreach($stockPlaces as $stockPlace) {
            foreach($stockPlace->compartments as $compartment) {
                $sectionIDs = array_merge([0], $compartment->sections->pluck('id')->toArray());

                foreach ($sectionIDs as $sectionID) {
                    $stockItemsCount = StockItem::where('article_number', $articleNumber)
                        ->where('stock_place_compartment_id', $compartment->id)
                        ->where('compartment_section_id', $sectionID)
                        ->count();

                    if ($stockItemsCount) {
                        $count += $stockItemsCount;
                        $pickingPlaces[] = $stockPlace->identifier . ':' . $compartment->identifier;

                        if ($count >= $quantity) {
                            return $pickingPlaces;
                        }
                    }
                }
            }
        }

        $pickingPlaces[] = '--';
        return $pickingPlaces;
    }
}
