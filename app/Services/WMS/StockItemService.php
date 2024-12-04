<?php

namespace App\Services\WMS;

use App\Models\StockItem;
use App\Models\StockPlaceCompartment;

class StockItemService
{
    public function allocateStockItem(StockItem $stockItem, string $allocationType, string $allocationReference, string $allocationDate = ''): array
    {
        if ($stockItem->allocation_type) {
            return [
                'success' => false,
                'message' => 'Stock item already allocated',
            ];
        }

        $stockItem->update([
            'allocation_type' => $allocationType,
            'allocation_reference' => $allocationReference,
            'allocation_date' => date('Y-m-d H:i:s', strtotime($allocationDate ?: 'now')),
        ]);

        return [
            'success' => true,
            'message' => 'Stock item allocated',
        ];
    }

    public function deallocateStockItem(StockItem $stockItem): array
    {
        $stockItem->update([
            'allocation_type' => null,
            'allocation_reference' => null,
            'allocation_date' => null,
        ]);

        return [
            'success' => true,
            'message' => 'Stock item deallocated',
        ];
    }

    public function addStockItem(string $articleNumber, StockPlaceCompartment $stockPlaceCompartment, int $quantity): array
    {
        $stockItems = [];

        for ($i = 0;$i < $quantity;$i++) {
            $stockItems[] = StockItem::create([
                'article_number' => $articleNumber,
                'stock_place_compartment_id' => $stockPlaceCompartment->id
            ]);

        }

        return [
            'success' => true,
            'message' => 'Stock items added',
            'stockItems' => $stockItems,
        ];
    }

    public function moveStockItem(StockItem $stockItem, StockPlaceCompartment $stockPlaceCompartment): array
    {
        $stockItem->update(['stock_place_compartment_id' => $stockPlaceCompartment->id]);

        return [
            'success' => true,
            'message' => 'Stock item moved',
        ];
    }

    public function removeStockItem(StockItem $stockItem): array
    {
        $stockItem->delete();

        return [
            'success' => true,
            'message' => 'Stock item removed',
        ];
    }
}
