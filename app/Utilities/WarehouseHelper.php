<?php

namespace App\Utilities;

use App\Enums\ShipmentInternalStatus;
use App\Models\CompartmentsTemplate;
use App\Models\StockItem;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use Illuminate\Support\Facades\DB;

class WarehouseHelper
{
    public static function getPickedStock(string $articleNumber): int
    {
        return (int) DB::table('shipment_lines')
            ->join('shipments', 'shipments.id', '=', 'shipment_lines.shipment_id')
            ->select('shipment_lines.picked_quantity')
            ->where('shipment_lines.article_number', $articleNumber)
            ->where('shipments.status', 'Open')
            ->where('shipments.operation', 'Issue')
            ->where('internal_status', '!=', ShipmentInternalStatus::OPEN->value)
            ->sum('shipment_lines.picked_quantity');
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
        $articleStock = (int) DB::table('articles')
            ->select('stock_manageable')
            ->where('article_number', $articleNumber)
            ->value('stock_manageable');

        $stockItemsByLocation = DB::table('stock_items')
            ->join('stock_place_compartments', 'stock_place_compartments.id', '=', 'stock_items.stock_place_compartment_id')
            ->join('stock_places', 'stock_places.id', '=', 'stock_place_compartments.stock_place_id')
            ->where('stock_items.article_number', $articleNumber)
            ->groupBy('stock_places.identifier', 'stock_place_compartments.identifier')
            ->select([
                'stock_places.identifier as stock_place_identifier',
                'stock_place_compartments.identifier as compartment_identifier',
                DB::raw('COUNT(*) as stock_count'),
            ])
            ->get();

        $locations = [];
        $managedStock = 0;

        foreach ($stockItemsByLocation as $locationRow) {
            $identifier = $locationRow->stock_place_identifier . ':' . $locationRow->compartment_identifier;

            if (!isset($locations[$identifier])) {
                $locations[$identifier] = [
                    'identifier' => $identifier,
                    'stock' => 0,
                    'last_inventory' => self::lastInventoryDate($articleNumber, $identifier)
                ];
            }

            $locations[$identifier]['stock'] += (int) $locationRow->stock_count;
            $managedStock += (int) $locationRow->stock_count;
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
        $pickedStock = self::getPickedStock($articleNumber);

        $colors = [
            self::classToColor('A'),
            self::classToColor('B'),
            self::classToColor('C'),
            self::classToColor('X'),
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
            ->whereNotIn('identifier', ['UTLEV'])
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

                $layer = 1;

                if ($compartment->template_id) {
                    $template = CompartmentsTemplate::find($compartment->template_id);

                    if ($template) {
                        if (str_contains($template->name, 'LVL2')) {
                            $layer = 2;
                        } elseif (str_contains($template->name, 'LVL3')) {
                            $layer = 3;
                        }
                    }
                }

                if ($stockItemsCount > 0) {
                    $identifier = $stockPlace->identifier . ':' . $compartment->identifier;
                    $articleLocations[] = [
                        'identifier' => $identifier,
                        'stock' => $stockItemsCount,
                        'class' => self::colorToClass($stockPlace->color),
                        'layer' => $layer,
                        'is_prio' => ($stockPlace->identifier === 'INLEV')
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
                'class' => 'C',
                'layer' => 1,
                'is_prio' => false
            ];
        }

        // Sort so that any is_prio comes before everything else
        usort($articleLocations, function ($a, $b) {
            // Prio first
            if ($a['is_prio'] !== $b['is_prio']) {
                return $a['is_prio'] ? -1 : 1;
            }

            // Lower layer number first
            if ($a['layer'] !== $b['layer']) {
                return $a['layer'] - $b['layer'];
            }

            // Tie-breaker: class A > B > C
            $classPriority = ['' => 0, 'A' => 1, 'B' => 2, 'C' => 3];
            return $classPriority[$a['class']] <=> $classPriority[$b['class']];
        });

        // If any single PRIO location alone is sufficient, return it immediately
        foreach ($articleLocations as $location) {
            if (!$location['is_prio']) break;

            if ($location['class'] === 'A') {
                if (!$isBoxCount && $quantity <= $location['stock']) {
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

        // Keep original single-compartment pass (already considers PRIO first due to sort)
        foreach ($articleLocations as $location) {
            if ($location['class'] === 'A') {
                if (!$isBoxCount && $quantity <= $location['stock']) {
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

        // Always drain PRIO first (all layers), then proceed with existing layer-first logic
        $collectedStock = 0;
        $identifiers = [];
        $quantities  = [];

        // 1) Drain INLEV locations first
        foreach ($articleLocations as $location) {
            if (!$location['is_prio']) break; // PRIO block is at the front

            $remaining = $quantity - $collectedStock;

            if ($remaining <= 0) break;

            $take = min($location['stock'], $remaining);

            if ($take > 0) {
                $collectedStock += $take;
                $identifiers[] = $location['identifier'];
                $quantities[]  = $take;
            }
        }

        if ($collectedStock >= $quantity) {
            return [
                'locations' => $identifiers,
                'quantity' => $quantities
            ];
        }

        // 2) Original: try to fulfill using only Layer 1 (excluding any already-consumed PRIO)
        $layer1Locations = array_filter(
            $articleLocations,
            fn($loc) => $loc['layer'] === 1 && !($loc['is_prio'] && in_array($loc['identifier'], $identifiers, true))
        );

        foreach ($layer1Locations as $location) {
            $remaining = $quantity - $collectedStock;

            if ($remaining <= 0) break;

            $take = min($location['stock'], $remaining);

            if ($take > 0) {
                $collectedStock += $take;
                $identifiers[] = $location['identifier'];
                $quantities[]  = $take;
            }
        }

        // 3) Original: if still not enough, include other layers
        if ($collectedStock < $quantity) {
            $otherLayers = array_filter(
                $articleLocations,
                fn($loc) => $loc['layer'] !== 1 && !($loc['is_prio'] && in_array($loc['identifier'], $identifiers, true))
            );

            foreach ($otherLayers as $location) {
                $remaining = $quantity - $collectedStock;

                if ($remaining <= 0) break;

                $take = min($location['stock'], $remaining);

                if ($take > 0) {
                    $collectedStock += $take;
                    $identifiers[] = $location['identifier'];
                    $quantities[]  = $take;
                }
            }
        }

        return [
            'locations' => $identifiers,
            'quantity'  => $quantities
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

            case 'X':
                return '#878787';
        }

        return '';
    }
}
