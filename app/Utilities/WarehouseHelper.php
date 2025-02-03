<?php

namespace App\Utilities;

use App\Models\StockItem;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
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
            $ids = DB::table('stock_place_compartments')
                ->select('id')
                ->where('stock_place_id', $stockPlaceID)
                ->pluck('id')
                ->toArray();

            $compartmentIDs = array_merge($compartmentIDs, $ids);
        }

        return DB::table('stock_items')
            ->whereIn('stock_place_compartment_id', $compartmentIDs)
            ->where('article_number', $articleNumber)
            ->exists();
    }

    public static function getStockPlaceAndCompartment(string $identifier): array|bool
    {
        if (!str_contains($identifier, ':')) {
            return false;
        }

        list($stockPlaceIdentifier, $compartmentIdentifier) = explode(':', $identifier);

        $stockPlace = StockPlace::where('identifier', $stockPlaceIdentifier)
            ->first();

        if (!$stockPlace) {
            return false;
        }

        $stockPlaceCompartment = StockPlaceCompartment::where('identifier', $compartmentIdentifier)
            ->where('stock_place_id', $stockPlace->id)
            ->first();

        if (!$stockPlaceCompartment) {
            return false;
        }

        return [
            'stock_place' => $stockPlace,
            'stock_place_compartment' => $stockPlaceCompartment
        ];
    }

    public static function getArticleStockAtLocation(string $articleNumber, string $identifier): int
    {
        $identifierData = self::getStockPlaceAndCompartment($identifier);
        $stockPlaceCompartment = $identifierData['stock_place_compartment'] ?? null;

        if (!$stockPlaceCompartment) {
            return 0;
        }

        return (int) StockItem::where('article_number', $articleNumber)
            ->where('stock_place_compartment_id', $stockPlaceCompartment->id)
            ->count();

    }

    public static function getArticleLocationsWithStock(string $articleNumber): array
    {
        $locations = [];

        $managedStock = 0;

        $articleStock = (int) DB::table('articles')
            ->select('stock_on_hand')
            ->where('article_number', $articleNumber)
            ->value('stock_on_hand');

        $stockItems = StockItem::where('article_number', $articleNumber)
            ->get();

        foreach ($stockItems as $stockItem) {
            $stockPlaceCompartment = StockPlaceCompartment::where('id', $stockItem->stock_place_compartment_id)
                ->first();

            $stockPlace = StockPlace::find($stockPlaceCompartment->stock_place_id);

            $identifier = $stockPlace->identifier . ':' . $stockPlaceCompartment->identifier;

            if (!isset($locations[$identifier])) {
                $locations[$identifier] = [
                    'identifier' => $identifier,
                    'stock' => 0
                ];
            }

            $locations[$identifier]['stock']++;

            $managedStock++;
        }

        if ($managedStock < $articleStock) {
            $locations['--'] = [
                'identifier' => '--',
                'stock' => $articleStock - $managedStock
            ];
        }

        return array_values($locations);
    }

    public static function getArticleLocations(string $articleNumber, int $quantity): array
    {
        $compartmentMaxPick = 0.5; // TODO: Make this a setting

        $colors = [
            self::classToColor('A'),
            self::classToColor('B'),
            self::classToColor('C'),
        ];

        $articleLocations = [];
        $managedStock = 0;

        $articleData = DB::table('articles')
            ->select('stock_on_hand', 'inner_box', 'master_box')
            ->where('article_number', $articleNumber)
            ->first();

        $masterBox = $articleData->master_box ?: 1;
        $innerBox = $articleData->inner_box ?: 1;
        $isBoxCount = (($masterBox > 1) && (($quantity % $masterBox) == 0)) || (($innerBox > 1) && (($quantity % $innerBox) == 0));

        $stockPlaces = StockPlace::whereIn('color', $colors)
            ->orderByRaw("FIELD(color, ?, ?, ?)", $colors)
            ->orderBy('identifier', 'ASC')
            ->where('is_active', 1)
            ->get();

        foreach ($stockPlaces as $stockPlace) {
            foreach ($stockPlace->compartments as $compartment) {
                $stockItemsCount = StockItem::where('article_number', $articleNumber)
                    ->where('stock_place_compartment_id', $compartment->id)
                    ->count();

                $identifier = $stockPlace->identifier . ':' . $compartment->identifier;

                if ($stockItemsCount > 0) {
                    $articleLocations[] = [
                        'identifier' => $identifier,
                        'stock' => $stockItemsCount,
                        'class' => self::colorToClass($stockPlace->color)
                    ];

                    $managedStock += $stockItemsCount;
                }
            }
        }

        $unmanagedStock = $articleData->stock_on_hand - $managedStock;
        if ($unmanagedStock > 0) {
            $articleLocations[] = [
                'identifier' => '--',
                'stock' => $unmanagedStock,
                'class' => 'C'
            ];
        }

        // Check if we can use a single compartment
        foreach ($articleLocations as $location) {
            if ($location['class'] === 'A') {
                if (!$isBoxCount && $quantity <= ($location['stock'] * $compartmentMaxPick)) {
                    return [$location['identifier']];
                }
            }
            else {
                if ($quantity <= $location['stock']) {
                    return [$location['identifier']];
                }
            }
        }

        // We must use a combination of compartments
        $collectedStock = 0;
        $identifiers = [];

        $articleLocations = array_reverse($articleLocations);
        foreach ($articleLocations as $location) {
            $collectedStock += $location['stock'];
            $identifiers[] = $location['identifier'];

            if ($collectedStock >= $quantity) {
                break;
            }
        }

        return $identifiers;
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
