<?php

namespace App\Utilities;

use App\Enums\ShipmentInternalStatus;
use App\Models\StockItem;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use Illuminate\Support\Facades\DB;

class WarehouseHelper
{
    public static function getPickedStock(string $articleNumber): int
    {
        $lines = DB::table('shipment_lines')
            ->join('shipments', 'shipments.id', '=', 'shipment_lines.shipment_id')
            ->select('picking_location', 'picking_location_quantity')
            ->where('shipment_lines.article_number', $articleNumber)
            ->where('shipments.status', 'Open')
            ->where('shipments.operation', 'Issue')
            ->where('internal_status', '!=', ShipmentInternalStatus::OPEN->value)
            ->where('picking_location', 'LIKE', '%"--"%')
            ->get();

        $pickedQty = 0;

        foreach ($lines as $line) {
            $locations = json_decode($line->picking_location);
            $quantities = json_decode($line->picking_location_quantity);

            for ($i = 0;$i < count($locations);$i++) {
                if ($locations[$i] !== '--') {
                    continue;
                }

                $pickedQty += ($quantities[$i] ?? 0);
            }
        }

        return $pickedQty;
    }

    public static function getReservedStock(string $articleNumber): int
    {
        return (int) DB::table('shipment_lines')
            ->join('shipments', 'shipments.id', '=', 'shipment_lines.shipment_id')
            ->select('shipment_lines.quantity')
            ->where('shipment_lines.article_number', $articleNumber)
            ->where('shipments.status', 'Open')
            ->where('shipments.operation', 'Issue')
            ->sum('shipment_lines.quantity');
    }

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

    public static function getUnmanagedStock(string $articleNumber): int
    {
        $locations = self::getArticleLocationsWithStock($articleNumber);

        foreach ($locations as $location) {
            if ($location['identifier'] == '--') {
                return $location['stock'];
            }
        }

        return 0;
    }

    public static function getArticleLocationsWithStock(string $articleNumber): array
    {
        $locations = [];

        $managedStock = 0;

        $articleStock = (int) DB::table('articles')
            ->select('stock_manageable')
            ->where('article_number', $articleNumber)
            ->value('stock_manageable');

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
                    'stock' => 0,
                    'last_inventory' => self::lastInventoryDate($articleNumber, $identifier)
                ];
            }

            $locations[$identifier]['stock']++;

            $managedStock++;
        }

        $locations['--'] = [
            'identifier' => '--',
            'stock' => $articleStock - $managedStock,
            'last_inventory' => '',
        ];

        return array_values($locations);
    }

    public static function getArticleLocations(string $articleNumber, int $quantity): array
    {
        $compartmentMaxPick = 0.5; // TODO: Make this a setting

        $pickedStock = self::getPickedStock($articleNumber);

        $colors = [
            self::classToColor('A'),
            self::classToColor('B'),
            self::classToColor('C'),
        ];

        $articleData = DB::table('articles')
            ->select('stock_manageable', 'inner_box', 'master_box')
            ->where('article_number', $articleNumber)
            ->first();

        if (!$articleData) {
            return [
                'locations' => [],
                'quantity' => []
            ];
        }

        $masterBox = $articleData->master_box ?: 1;
        $innerBox = $articleData->inner_box ?: 1;
        $isBoxCount = (($masterBox > 1) && (($quantity % $masterBox) == 0)) || (($innerBox > 1) && (($quantity % $innerBox) == 0));

        // Fetch stock places with compartments and items in a single query
        $stockPlaces = StockPlace::whereIn('color', $colors)
            ->where('is_active', 1)
            ->with(['compartments.stockItems' => function ($query) use ($articleNumber) {
                $query->where('article_number', $articleNumber);
            }])
            ->get()
            ->sortBy(function ($stockPlace) use ($colors) {
                return array_search($stockPlace->color, $colors);
            });

        $articleLocations = [];
        $managedStock = 0;

        // Process stock places and compartments
        foreach ($stockPlaces as $stockPlace) {
            foreach ($stockPlace->compartments as $compartment) {
                $stockItemsCount = $compartment->stockItems->count();

                if ($stockItemsCount > 0) {
                    $identifier = $stockPlace->identifier . ':' . $compartment->identifier;
                    $articleLocations[] = [
                        'identifier' => $identifier,
                        'stock' => $stockItemsCount,
                        'class' => self::colorToClass($stockPlace->color)
                    ];
                    $managedStock += $stockItemsCount;
                }
            }
        }

        // Add unmanaged stock
        $unmanagedStock = $articleData->stock_manageable - $managedStock - $pickedStock;

        if ($unmanagedStock > 0) {
            $articleLocations[] = [
                'identifier' => '--',
                'stock' => $unmanagedStock,
                'class' => 'C'
            ];
        }

        // Check if a single compartment is sufficient
        foreach ($articleLocations as $location) {
            if ($location['class'] === 'A') {
                if (!$isBoxCount && $quantity <= ($location['stock'] * $compartmentMaxPick)) {
                    return [
                        'locations' => [$location['identifier']],
                        'quantity' => [$quantity]
                    ];
                }
            } else {
                if ($quantity <= $location['stock']) {
                    return [
                        'locations' => [$location['identifier']],
                        'quantity' => [$quantity]
                    ];
                }
            }
        }

        // If multiple compartments are needed, aggregate stock
        $collectedStock = 0;
        $identifiers = [];
        $quantities = [];

        foreach (array_reverse($articleLocations) as $location) {
            $remaining = $quantity - $collectedStock;
            $take = min($location['stock'], $remaining);

            $collectedStock += $take;
            $identifiers[] = $location['identifier'];
            $quantities[] = $take;

            if ($collectedStock >= $quantity) {
                break;
            }
        }

        return [
            'locations' => $identifiers,
            'quantity' => $quantities
        ];
    }

    public static function lastInventoryDate(string $articleNumber, string $identifier): string
    {
        return (string) DB::table('stock_keep_transactions')
            ->select('created_at')
            ->where('article_number', '=', $articleNumber)
            ->where('identifiers', 'LIKE', '%' . $identifier . '%')
            ->where('status', '=', 'completed')
            ->orderBy('created_at', 'DESC')
            ->value('created_at');
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
