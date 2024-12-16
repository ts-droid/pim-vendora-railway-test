<?php

namespace App\Services\WMS;

use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;

class StockPlaceService
{
    public function createStockPlace(array $data): array
    {
        if (empty($data['identifier'])) {
            return array('success' => false, 'message' => 'Identifier is required');
        }

        // Make sure identifier is unique
        if (StockPlace::where('identifier', $data['identifier'])->exists()) {
            return array('success' => false, 'message' => 'Identifier is not unique');
        }

        $stockPlace = StockPlace::create([
            'identifier' => (string) $data['identifier'],
            'map_position_x' => (int) $data['map_position_x'],
            'map_position_y' => (int) $data['map_position_y'],
            'map_size_x' => (int) $data['map_size_x'],
            'map_size_y' => (int) $data['map_size_y'],
            'color' => (string) ($data['color'] ?? '#878787'),
            'type' => (int) ($data['type'] ?? 1),
            'is_active' => 0,
        ]);

        return array('success' => true, 'stockPlace' => $stockPlace);
    }

    public function updateStockPlace(StockPlace $stockPlace, array $data): StockPlace
    {
        if (isset($data['identifier'])) {
            if (StockPlace::where('identifier', $data['identifier'])->exists()) {
                unset($data['identifier']);
            }
        }

        $stockPlace->update($data);

        return $stockPlace;
    }

    public function deleteStockPlace(StockPlace $stockPlace): array
    {
        if ($stockPlace->compartments) {
            foreach ($stockPlace->compartments as $compartment) {
                if ($compartment->stockItems()->exists()) {
                    return array('success' => false, 'message' => 'Stock place is not empty');
                }
            }

            foreach ($stockPlace->compartments as $compartment) {
                $this->deleteStockPlaceCompartment($compartment);
            }
        }

        // Delete stock place
        $stockPlace->delete();

        return ['success' => true];
    }

    public function createStockPlaceCompartment(StockPlace $stockPlace, array $data): array
    {
        // Calculate new identifier
        $compartments = StockPlaceCompartment::where('stock_place_id', $stockPlace->id)->get();
        if ($compartments->count() > 0) {
            $maxIdentifier = 0;
            foreach ($compartments as $compartment) {
                if ($compartment->identifier > $maxIdentifier) {
                    $maxIdentifier = $compartment->identifier;
                }
            }

            $identifier = $maxIdentifier + 1;
        }
        else {
            $identifier = 1;
        }

        $stockPlaceCompartment = StockPlaceCompartment::create([
            'stock_place_id' => $stockPlace->id,
            'volume_class' => (string) ($data['volume_class'] ?? ''),
            'identifier' => (string) $identifier,
            'width' => (float) $data['width'],
            'height' => (float) $data['height'],
            'depth' => (float) $data['depth'],
            'is_truck' => (int) ($data['is_truck'] ?? 0),
            'is_movable' => (int) ($data['is_movable'] ?? 0),
            'is_walk_through' => (int) ($data['is_walk_through'] ?? 0),
            'is_manual' => (int) ($data['is_manual'] ?? 0)
        ]);

        return array('success' => true, 'stockPlaceCompartment' => $stockPlaceCompartment);
    }

    public function updateStockPlaceCompartment(StockPlaceCompartment $stockPlaceCompartment, array $data): StockPlaceCompartment
    {
        $stockPlaceCompartment->update($data);

        return $stockPlaceCompartment;
    }

    public function deleteStockPlaceCompartment(StockPlaceCompartment $stockPlaceCompartment): array
    {
        if ($stockPlaceCompartment->stockItems()->exists()) {
            return [
                'success' => false,
                'message' => 'Stock compartment is not empty'
            ];
        }

        $stockPlaceCompartment->delete();

        return ['success' => true];
    }
}
