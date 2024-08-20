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
            'name' => (string) $data['name'],
            'map_position_x' => (int) $data['map_position_x'],
            'map_position_y' => (int) $data['map_position_y'],
            'map_size_x' => (int) $data['map_size_x'],
            'map_size_y' => (int) $data['map_size_y'],
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
        // TODO: Make sure no stock item is in this place

        $stockPlace->delete();

        return array('success' => true);
    }

    public function createStockPlaceCompartment(StockPlace $stockPlace, array $data): array
    {
        if (empty($data['identifier'])) {
            return array('success' => false, 'message' => 'Identifier is required');
        }

        // Make sure identifier is unique
        if (StockPlaceCompartment::where('identifier', $data['identifier'])->where('stock_place_id', $stockPlace->id)->exists()) {
            return array('success' => false, 'message' => 'Identifier is not unique');
        }

        $stockPlaceCompartment = StockPlaceCompartment::create([
            'stock_place_id' => $stockPlace->id,
            'identifier' => (string) $data['identifier'],
            'width' => (float) $data['width'],
            'height' => (float) $data['height'],
            'depth' => (float) $data['depth'],
        ]);

        return array('success' => true, 'stockPlaceCompartment' => $stockPlaceCompartment);
    }

    public function updateStockPlaceCompartment(StockPlaceCompartment $stockPlaceCompartment, array $data): StockPlaceCompartment
    {
        if (isset($data['identifier'])) {
            if (StockPlaceCompartment::where('identifier', $data['identifier'])->where('stock_place_id', $stockPlaceCompartment->stock_place_id)->exists()) {
                unset($data['identifier']);
            }
        }

        $stockPlaceCompartment->update($data);

        return $stockPlaceCompartment;
    }

    public function deleteStockPlaceCompartment(StockPlaceCompartment $stockPlaceCompartment): array
    {
        // TODO: Make sure no stock item is in this compartment

        $stockPlaceCompartment->delete();

        return array('success' => true);
    }
}
