<?php

namespace App\Services\WMS;

use App\Models\StockItem;
use App\Models\StockItemLog;
use App\Models\StockItemMovement;
use App\Models\StockPlaceCompartment;
use App\Utilities\EventLogger;
use App\Utilities\WarehouseHelper;
use Illuminate\Support\Facades\DB;

class StockItemService
{
    public function getStockItemsFromCompartment(StockPlaceCompartment $stockPlaceCompartment, string $articleNumber, int $quantity)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        return StockItem::where('stock_place_compartment_id', $stockPlaceCompartment->id)
            ->where('article_number', $articleNumber)
            ->limit($quantity)
            ->get();
    }

    public function addStockItem(string $articleNumber, int $quantity, StockPlaceCompartment $stockPlaceCompartment, string $signature = '', string $source = ''): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        DB::beginTransaction();

        try {
            $stockItems = [];

            for ($i = 0;$i < $quantity;$i++) {
                $stockItems[] = StockItem::create([
                    'article_number' => $articleNumber,
                    'stock_place_compartment_id' => $stockPlaceCompartment->id
                ]);
            }

            $this->updateStockMovements([$stockPlaceCompartment]);

            $this->logChange($articleNumber, $stockPlaceCompartment->id, $quantity, $signature, $source);

            $displayName = $signature ?: 'System';
            $identifier = ($stockPlaceCompartment->stockPlace->identifier ?? '') . ':' . ($stockPlaceCompartment->identifier);

            EventLogger::logAction(
                $displayName . ' inserted ' . $quantity . ' pcs of <article>' . $articleNumber . '</article> into <stockplace>' . $identifier . '</stockplace>. Provided source: ' . ($source ?: 'none'),
                $displayName,
                [
                    'article_number' => $articleNumber,
                    'to_compartment_id' => $stockPlaceCompartment->id
                ]
            );

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

    public function moveStockItems(string $articleNumber, int $quantity, StockPlaceCompartment $fromStockPlaceCompartment, StockPlaceCompartment $toStockPlaceCompartment, string $signature = '', string $source = '', bool $logEvent = true): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

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

        $log = [];

        foreach ($stockItems as $stockItem) {
            $response = $this->moveStockItem($stockItem, $toStockPlaceCompartment, $signature, $source, false);

            if ($response['success']) {
                foreach ($response['log'] as $compartmentID => $movedQty) {
                    if (!isset($log[$compartmentID])) {
                        $log[$compartmentID] = 0;
                    }

                    $log[$compartmentID] += $movedQty;
                }
            }
        }

        foreach ($log as $compartmentID => $movedQty) {
            $this->logChange($articleNumber, $compartmentID, $movedQty, $signature, $source);

            if ($logEvent) {
                $fromIdentifier = ($fromStockPlaceCompartment->stockPlace->identifier ?? '') . ':' . $fromStockPlaceCompartment->identifier;
                $toIdentifier = ($toStockPlaceCompartment->stockPlace->identifier ?? '') . ':' . $toStockPlaceCompartment->identifier;

                EventLogger::logAction(
                    $signature . ' moved ' . $movedQty . ' pcs of <article>' . $articleNumber . '</article> from <stockplace>' . $fromIdentifier . '</stockplace> to <stockplace>' . $toIdentifier . '</stockplace>',
                    $signature,
                    [
                        'article_number' => $articleNumber,
                        'from_compartment_id' => $fromStockPlaceCompartment->id,
                        'to_compartment_id' => $toStockPlaceCompartment->id
                    ]
                );
            }
        }

        return [
            'success' => true,
            'message' => 'Stock items moved',
        ];
    }

    public function moveStockItem(StockItem $stockItem, StockPlaceCompartment $stockPlaceCompartment, string $signature = '', string $source = '', bool $storeLog = true): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        DB::beginTransaction();

        $log = [];

        try {
            $oldStockPlaceCompartment = StockPlaceCompartment::find($stockItem->stock_place_compartment_id);

            if ($storeLog) {
                $this->logChange($stockItem->article_number, $stockItem->stock_place_compartment_id, -1, $signature, $source);
                $this->logChange($stockItem->article_number, $stockPlaceCompartment->id, 1, $signature, $source);
            }

            $log[$stockItem->stock_place_compartment_id] = -1;
            $log[$stockPlaceCompartment->id] = 1;

            $stockItem->update([
                'stock_place_compartment_id' => $stockPlaceCompartment->id
            ]);


            $this->deleteTemporaryCompartments($stockPlaceCompartment->id);

            DB::commit();

            $this->updateStockMovements([$oldStockPlaceCompartment, $stockPlaceCompartment]);

            return [
                'success' => true,
                'message' => 'Stock item moved',
                'log' => $log
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to move stock item: ' . $e->getMessage(),
            ];
        }
    }

    public function removeStockItems($stockItems, string $signature = '', $source = '', bool $storeLog = true): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        DB::beginTransaction();

        try {
            $totalRemoved = [];
            $stockPlaceCompartments = [];

            foreach ($stockItems as $stockItem) {
                if (!isset($totalRemoved[$stockItem->article_number])) {
                    $totalRemoved[$stockItem->article_number] = [];

                    if (!isset($totalRemoved[$stockItem->article_number][$stockItem->stock_place_compartment_id])) {
                        $totalRemoved[$stockItem->article_number][$stockItem->stock_place_compartment_id] = 0;
                    }
                }

                $totalRemoved[$stockItem->article_number][$stockItem->stock_place_compartment_id] += 1;

                $this->deleteTemporaryCompartments($stockItem->stock_place_compartment_id);

                $stockItem->delete();
            }

            foreach ($totalRemoved as $articleNumber => $groups) {
                foreach ($groups as $stockPlaceCompartmentId => $quantity) {
                    $this->logChange($articleNumber, $stockPlaceCompartmentId, (-1 * $quantity), $signature, $source);

                    if ($storeLog) {
                        $stockPlaceCompartment = StockPlaceCompartment::find($stockPlaceCompartmentId);
                        $identifier = ($stockPlaceCompartment->stockPlace->identifier ?? '') . ':' . ($stockPlaceCompartment->identifier ?? '');

                        EventLogger::logAction(
                            $signature . ' removed ' . $quantity . ' pcs of <article>' . $articleNumber . '</article> from <stockplace>' . $identifier . '</stockplace>',
                            $signature,
                            [
                                'article_number' => $articleNumber,
                                'from_compartment_id' => $stockPlaceCompartmentId
                            ]
                        );
                    }

                    if (!isset($stockPlaceCompartments[$stockPlaceCompartmentId])) {
                        $stockPlaceCompartments[$stockPlaceCompartmentId] = StockPlaceCompartment::find($stockPlaceCompartmentId);
                    }
                }
            }

            DB::commit();

            $stockPlaceCompartments = array_values($stockPlaceCompartments);
            $this->updateStockMovements($stockPlaceCompartments);

            return [
                'success' => true,
                'message' => 'Stock items removed',
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
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

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
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

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

    public function updateAllStockMovements()
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $stockPlaceCompartments = StockPlaceCompartment::all();

        $array = [];
        foreach($stockPlaceCompartments as $compartment) {
            $array[] = $compartment;
        }

        $this->updateStockMovements($array);
    }

    private function updateStockMovements(array $stockPlaceCompartments)
    {
        $stockOptimizationManager = new StockOptimizationManager();
        $warehouseHelper = new WarehouseHelper();

        foreach ($stockPlaceCompartments as $stockPlaceCompartment) {
            if (!($stockPlaceCompartment instanceof StockPlaceCompartment)) continue;

            $stockItems = StockItem::where('stock_place_compartment_id', $stockPlaceCompartment->id)
                ->get();

            if ($stockItems && $stockItems->count() > 0) {
                $articleNumbers = $stockItems->pluck('article_number')->toArray();
                $articleNumbers = array_count_values($articleNumbers);
            }
            else {
                $articleNumbers = [];
            }

            // Check if the compartment is marked for unleash
            if ($stockPlaceCompartment->unleash) {
                foreach ($articleNumbers as $articleNumber => $count) {
                    $movement = StockItemMovement::where('article_number', $articleNumber)
                        ->where('from_stock_place_compartment', $stockPlaceCompartment->id)
                        ->where('type', 'unleash')
                        ->first();

                    if ($movement) {
                        $movement->update(['quantity' => $count]);
                    }
                    else {
                        $stockOptimizationManager->makeStockMovement(
                            $articleNumber,
                            $stockPlaceCompartment->id,
                            0,
                            $count,
                            StockOptimizationManager::MOVEMENT_TYPE_UNLEASH
                        );
                    }
                }
            }

            // Check if the compartment have any unleash movements
            foreach ($articleNumbers as $articleNumber => $count) {
                $movement = StockItemMovement::where('article_number', $articleNumber)
                    ->where('from_stock_place_compartment', $stockPlaceCompartment->id)
                    ->where('type', 'unleash')
                    ->first();

                if ($movement) {
                    $movement->update(['quantity' => $count]);
                }
            }

            // Check all movements on the way from this compartment
            $movementsFrom = StockItemMovement::where('from_stock_place_compartment', $stockPlaceCompartment->id)->get();
            if ($movementsFrom) {
                foreach ($movementsFrom as $movement) {

                    $existingCount = $articleNumbers[$movement->article_number] ?? 0;

                    // Remove the movement if the article no longer exists in the compartment
                    if ($existingCount == 0) {
                        $movement->delete();
                        continue;
                    }

                    // If the system want to move more items than there are in the compartment, update the quantity to move
                    if ($movement->quantity > $existingCount) {
                        $movement->update(['quantity' => $existingCount]);
                    }

                }
            }

            // Check all movements on the way to this compartment
            $movementsTo = StockItemMovement::where('to_stock_place_compartment', $stockPlaceCompartment->id)->get();
            if ($movementsTo) {
                foreach ($movementsTo as $movement) {

                    $locations = $warehouseHelper->getArticleLocationsWithStock($movement->article_number);

                    // If moving from unknown compartment, make sure there is enough stock
                    if ($movement->from_stock_place_compartment == 0) {
                        $unmanagedStock = 0;
                        foreach ($locations as $location) {
                            if ($location['identifier'] == '--') {
                                $unmanagedStock = $location['stock'];
                                break;
                            }
                        }

                        if ($unmanagedStock == 0) {
                            $movement->delete();
                        } else if ($unmanagedStock < $movement->quantity) {
                            $movement->update(['quantity' => $unmanagedStock]);
                        }
                    }

                }
            }
        }
    }

    private function deleteTemporaryCompartments(int $stockPlaceCompartmentID): void
    {
        $compartment = StockPlaceCompartment::find($stockPlaceCompartmentID);

        if (!$compartment) return;

        if (!$compartment->stockPlace->is_temporary) return;

        $hasItems = StockItem::where('stock_place_compartment_id', $compartment->id)->exists();
        if ($hasItems) return;

        // The compartment is empty, so we can remove it
        $compartment->delete();
    }

    private function logChange(string $articleNumber, int $stockPlaceCompartmentID, int $quantity, string $signature = '', string $source = ''): void
    {
        StockItemLog::create([
            'article_number' => $articleNumber,
            'stock_place_compartment_id' => $stockPlaceCompartmentID,
            'quantity' => $quantity,
            'signature' => $signature,
            'source' => $source
        ]);
    }
}
