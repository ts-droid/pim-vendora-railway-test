<?php

namespace App\Services\WMS;

use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Models\StockPlaceTemplate;

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

        $this->pushToTemplate($stockPlace);

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

        $this->pushToTemplate($stockPlaceCompartment->stockPlace);

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

    public function createStockPlaceTemplate(StockPlace $stockPlace, string $name): StockPlaceTemplate
    {
        $stockPlaceTemplate = StockPlaceTemplate::create([
            'name' => $name,
            'stock_place' => $stockPlace->toArray(),
            'stock_place_compartments' => $stockPlace->compartments->toArray()
        ]);

        $stockPlace->update(['template_id' => $stockPlaceTemplate->id]);

        return $stockPlaceTemplate;
    }

    public function copyStockPlaceTemplate(StockPlaceTemplate $stockPlaceTemplate, string $identifier, int $positionX, int $positionY): array
    {
        // First create the stock place
        $stockPlaceData = $stockPlaceTemplate->stock_place;

        $stockPlaceData['identifier'] = $identifier;
        $stockPlaceData['map_position_x'] = $positionX;
        $stockPlaceData['map_position_y'] = $positionY;

        unset($stockPlaceData['id']);
        unset($stockPlaceData['created_at']);
        unset($stockPlaceData['updated_at']);

        $response = $this->createStockPlace($stockPlaceData);
        if (!$response['success']) {
            return $response;
        }

        $stockPlace = $response['stockPlace'];
        $stockPlace->update(['template_id' => $stockPlaceTemplate->id]);

        // Then create the compartments
        $compartments = $stockPlaceTemplate->stock_place_compartments ?: [];

        if ($compartments) {
            $compartments = array_reverse($compartments);

            for ($i = 1;$i <= count($compartments);$i++) {
                $compartmentData = $compartments[$i - 1];

                $compartmentData['identifier'] = $i;

                unset($compartmentData['id']);
                unset($compartmentData['stock_place_id']);
                unset($compartmentData['created_at']);
                unset($compartmentData['updated_at']);

                $this->createStockPlaceCompartment($stockPlace, $compartmentData);
            }
        }

        return [
            'success' => true,
            'stockPlace' => $stockPlace
        ];
    }

    private function pushToTemplate(StockPlace $stockPlace)
    {
        if (!$stockPlace->template_id) {
            return;
        }

        $template = StockPlaceTemplate::find('id', $stockPlace->template_id);
        if (!$template) {
            return;
        }

        $stockPlace = StockPlace::find($stockPlace->id);

        $template->update([
            'stock_place' => $stockPlace->toArray(),
            'stock_place_compartments' => $stockPlace->compartments->toArray()
        ]);

        $siblings = StockPlace::where('template_id', $template->id)
            ->where('id', '!=', $stockPlace->id)
            ->get();

        if ($siblings) {
            foreach ($siblings as $sibling) {
                $this->pushTemplateToStockPlace($template, $sibling);
            }
        }
    }

    private function pushTemplateToStockPlace(StockPlaceTemplate $template, StockPlace $stockPlace)
    {
        // Update the stock place
        $stockPlaceData = $template->stock_place;

        unset($stockPlaceData['id']);
        unset($stockPlaceData['created_at']);
        unset($stockPlaceData['updated_at']);
        unset($stockPlaceData['identifier']);
        unset($stockPlaceData['map_position_x']);
        unset($stockPlaceData['map_position_y']);

        $stockPlace->update($stockPlaceData);

        // Update compartments
        $templateCompartments = $template->stock_place_compartments ?: [];

        foreach ($templateCompartments as $templateCompartment) {
            $stockPlaceCompartment = StockPlaceCompartment::where('stock_place_id', $stockPlace->id)
                ->where('identifier', $templateCompartment['identifier'])
                ->first();

            if (!$stockPlaceCompartment) {
                continue;
            }

            unset($templateCompartment['id']);
            unset($templateCompartment['identifier']);
            unset($templateCompartment['stock_place_id']);
            unset($templateCompartment['created_at']);
            unset($templateCompartment['updated_at']);

            $stockPlaceCompartment->update($templateCompartment);
        }
    }
}
