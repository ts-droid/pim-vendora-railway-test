<?php

namespace App\Services\WMS;

use App\Models\CompartmentSection;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;

class StockPlaceService
{
    public function getCompartmentByIdentifier(string $identifier): ?StockPlaceCompartment
    {
        $split = explode(':', $identifier);
        $stockPlaceIdentifier = $split[0];
        $compartmentIdentifier = $split[1];

        $stockPlace = StockPlace::where('identifier', $stockPlaceIdentifier)->first();
        if (!$stockPlace) {
            return null;
        }

        $compartment = StockPlaceCompartment::where('stock_place_id', $stockPlace->id)
            ->where('identifier', $compartmentIdentifier)
            ->first();

        if (!$compartment) {
            return null;
        }

        return $compartment;
    }

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
            'is_active' => (int) ($data['is_active'] ?? 0),
            'is_temporary' => (int) ($data['is_temporary'] ?? 0),
            'is_virtual' => (int) ($data['is_virtual'] ?? 0),
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

                foreach ($compartment->sections as $section) {
                    if ($section->stockItems()->exists()) {
                        return array('success' => false, 'message' => 'Stock place is not empty');
                    }
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

    public function createStockPlaceCompartment(StockPlace $stockPlace, array $data, int $compartmentsLevel = 1): array
    {
        $minIdentifier = 1;
        $maxIdentifier = 19;

        if ($compartmentsLevel >= 2) {
            $minIdentifier = $compartmentsLevel * 10;
            $maxIdentifier = ($compartmentsLevel * 10) + 9;
        }

        // Fetch existing compartments for this stock place
        $compartments = StockPlaceCompartment::where('stock_place_id', $stockPlace->id)->get();

        // Filter only compartments within the valid range
        $validCompartments = $compartments->filter(function ($compartment) use ($minIdentifier, $maxIdentifier) {
            return $compartment->identifier >= $minIdentifier && $compartment->identifier <= $maxIdentifier;
        });

        // Calculate the next available identifier within the range
        if ($validCompartments->count() > 0) {
            $maxExistingIdentifier = $validCompartments->max('identifier');
            $identifier = $maxExistingIdentifier + 1;

            // If identifier goes out of bounds, throw an error or handle overflow
            if ($identifier > $maxIdentifier) {
                return [
                    'success' => false,
                    'message' => 'Maximum number of compartments reached for this level.'
                ];
            }
        } else {
            $identifier = $minIdentifier;
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
            'is_manual' => (int) ($data['is_manual'] ?? 0),
            'template_id' => (int) ($data['template_id'] ?? 0),
            'template_group' => (int) ($data['template_group'] ?? 0),
        ]);

        return [
            'success' => true,
            'stockPlaceCompartment' => $stockPlaceCompartment
        ];
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

        foreach ($stockPlaceCompartment->sections as $section) {
            if ($section->stockItems()->exists()) {
                return [
                    'success' => false,
                    'message' => 'Section is not empty'
                ];
            }
        }

        foreach ($stockPlaceCompartment->sections as $section) {
            $section->delete();
        }

        $stockPlaceCompartment->delete();

        return ['success' => true];
    }
}
