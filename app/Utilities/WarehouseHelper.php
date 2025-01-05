<?php

namespace App\Utilities;

use App\Models\StockItem;
use App\Models\StockPlace;
use Illuminate\Support\Facades\DB;

class WarehouseHelper
{
    public static function articleHasPlacement(string $articleNumber, array $classes): bool
    {
        $colors = [];
        foreach ($classes as $class) {
            $colors[] = self::classToColor($class);
        }

        $stockPlaceIDs = DB::table('stock_places')
            ->select('id')
            ->whereIn('color', $colors)
            ->pluck('id');

        $compartmentIDs = [];

        foreach ($stockPlaceIDs as $stockPlaceID) {
            $compartmentIDs[] = DB::table('stock_place_compartments')
                ->select('id')
                ->where('stock_place_id', $stockPlaceID)
                ->pluck('id');
        }

        return DB::table('stock_items')
            ->whereIn('stock_place_compartment_id', $compartmentIDs)
            ->where('article_number', $articleNumber)
            ->exists();
    }

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
        $managedStockCount = 0;

        foreach($stockPlaces as $stockPlace) {
            foreach($stockPlace->compartments as $compartment) {
                $sectionIDs = array_merge([0], $compartment->sections->pluck('id')->toArray());

                foreach ($sectionIDs as $sectionID) {
                    $stockItemsCount = StockItem::where('article_number', $articleNumber)
                        ->where('stock_place_compartment_id', $compartment->id)
                        ->where('compartment_section_id', $sectionID)
                        ->count();

                    $managedStockCount += $stockItemsCount;

                    if ($stockItemsCount >= $quantity) {
                        return [$stockPlace->identifier . ':' . $compartment->identifier];
                    }
                }
            }
        }

        // Check if unplaced articles can deliver the entire quantity
        $articleStock = DB::table('articles')
            ->select('stock')
            ->where('article_number', $articleNumber)
            ->value('stock');

        $unmanagedStock = $articleStock - $managedStockCount;

        if ($unmanagedStock >= $quantity) {
            return ['--'];
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

    public static function colorToClass(string $color): string
    {
        switch ($color) {
            case '#50f25b':
                return 'A';

            case '#f2a950':
                return 'B';

            case '#f2505f':
                return 'C';
        }

        return '';
    }

    public static function classToColor(string $class): string
    {
        switch ($class) {
            case 'A':
                return '#50f25b';

            case 'B':
                return '#f2a950';

            case 'C':
                return '#f2505f';
        }

        return '';
    }
}
