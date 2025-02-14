<?php

namespace App\Services\WMS;

use App\Models\StockItem;
use App\Models\StockItemLog;
use App\Models\StockPlaceCompartment;
use Illuminate\Support\Facades\DB;

class StockItemService
{
    public function getStockItemsFromCompartment(StockPlaceCompartment $stockPlaceCompartment, string $articleNumber, int $quantity)
    {
        return StockItem::where('stock_place_compartment_id', $stockPlaceCompartment->id)
            ->where('article_number', $articleNumber)
            ->limit($quantity)
            ->get();
    }

    public function addStockItem(string $articleNumber, int $quantity, StockPlaceCompartment $stockPlaceCompartment, string $signature = ''): array
    {
        DB::beginTransaction();

        try {
            $stockItems = [];

            for ($i = 0;$i < $quantity;$i++) {
                $stockItems[] = StockItem::create([
                    'article_number' => $articleNumber,
                    'stock_place_compartment_id' => $stockPlaceCompartment->id
                ]);
            }

            $this->logChange($articleNumber, $stockPlaceCompartment->id, $quantity, $signature);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Stock items added',
                'stockItems' => $stockItems,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to add stock items: ' . $e->getMessage(),
            ];
        }
    }

    public function moveStockItems(string $articleNumber, int $quantity, StockPlaceCompartment $fromStockPlaceCompartment, StockPlaceCompartment $toStockPlaceCompartment, string $signature = ''): array
    {
        $stockItems = StockItem::where('article_number', $articleNumber)
            ->where('stock_place_compartment_id', $fromStockPlaceCompartment->id)
            ->limit($quantity)
            ->get();

        if ($stockItems->count() != $quantity) {
            return [
                'success' => false,
                'message' => 'Not enough stock items to perform the move',
            ];
        }

        foreach ($stockItems as $stockItem) {
            $this->moveStockItem($stockItem, $toStockPlaceCompartment, $signature);
        }

        return [
            'success' => true,
            'message' => 'Stock items moved',
        ];
    }

    public function moveStockItem(StockItem $stockItem, StockPlaceCompartment $stockPlaceCompartment, string $signature = ''): array
    {
        DB::beginTransaction();

        try {
            $this->logChange($stockItem->article_number, $stockItem->stock_place_compartment_id, -1, $signature);
            $this->logChange($stockItem->article_number, $stockPlaceCompartment->id, 1, $signature);

            $stockItem->update([
                'stock_place_compartment_id' => $stockPlaceCompartment->id
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Stock item moved',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to move stock item: ' . $e->getMessage(),
            ];
        }
    }

    public function removeStockItem(StockItem $stockItem, string $signature = ''): array
    {
        DB::beginTransaction();

        try {
            $this->logChange($stockItem->article_number, $stockItem->stock_place_compartment_id, -1, $signature);

            $stockItem->delete();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Stock item removed',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to remove stock item: ' . $e->getMessage(),
            ];
        }
    }

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

    private function logChange(string $articleNumber, int $stockPlaceCompartmentID, int $quantity, string $signature = ''): void
    {
        StockItemLog::create([
            'article_number' => $articleNumber,
            'stock_place_compartment_id' => $stockPlaceCompartmentID,
            'quantity' => $quantity,
            'signature' => $signature,
        ]);
    }
}
