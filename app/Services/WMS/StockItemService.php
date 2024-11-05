<?php

namespace App\Services\WMS;

use App\Models\StockItem;
use App\Models\StockPlaceCompartment;

class StockItemService
{
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
            'stockItems' => $stockItems
        ];
    }

    public function moveItems(array $articleNumber, StockPlaceCompartment $stockPlaceCompartment, StockPlaceCompartment $toStockPlaceCompartment): array
    {

    }

    public function moveStockItem(StockItem $stockItem, StockPlaceCompartment $stockPlaceCompartment): array
    {
        $stockItem->update(['stock_place_compartment_id' => $stockPlaceCompartment->id]);

        return [
            'success' => true
        ];
    }

    public function removeStockItem(StockItem $stockItem): array
    {
        $stockItem->delete();

        return [
            'success' => true,
        ];
    }
}
