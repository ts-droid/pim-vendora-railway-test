<?php

namespace App\Services\WMS;

use App\Models\StockPlace;

class StockPlaceService
{
    public function createStockPlace(array $data): StockPlace
    {
        return StockPlace::create([
            'identifier' => (string) $data['identifier'],
            'name' => (string) $data['name'],
            'map_position_x' => (int) $data['map_position_x'],
            'map_position_y' => (int) $data['map_position_y'],
            'map_size_x' => (int) $data['map_size_x'],
            'map_size_y' => (int) $data['map_size_y'],
        ]);
    }

    public function updateStockPlace(StockPlace $stockPlace, array $data): StockPlace
    {
        $stockPlace->update($data);

        return $stockPlace;
    }

    public function deleteStockPlace(StockPlace $stockPlace): bool
    {
        // TODO: Make sure no stock item is in this place

        $stockPlace->delete();

        return true;
    }
}
