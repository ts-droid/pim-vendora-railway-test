<?php

namespace App\Http\Controllers;

use App\Models\CompartmentSection;
use App\Models\CompartmentsTemplate;
use App\Models\StockItem;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Models\StockPlaceGroup;
use App\Services\WMS\StockPlaceService;
use Illuminate\Http\Request;

class StockPlaceController extends Controller
{
    public function getStockPlaces(Request $request)
    {
        $stockPlaces = StockPlace::with('compartments', 'compartments.sections')->get();

        $stockPlacesArray = [];
        foreach ($stockPlaces as $stockPlace) {
            $stockPlaceArray = $stockPlace->toArray();
            $stockPlaceArray['is_walk_through'] = $stockPlace->is_walk_through();

            $stockPlacesArray[] = $stockPlaceArray;
        }

        return ApiResponseController::success($stockPlacesArray);
    }

    public function getStockPlace(Request $request, StockPlace $stockPlace)
    {
        $stockPlace->load('compartments', 'compartments.sections');

        $stockPlaceArray = $stockPlace->toArray();
        $stockPlaceArray['is_walk_through'] = $stockPlace->is_walk_through();

        return ApiResponseController::success($stockPlaceArray);
    }

    public function storeStockPlace(Request $request)
    {
        $stockPlaceService = new StockPlaceService();
        $response = $stockPlaceService->createStockPlace($request->only(
            'identifier',
            'map_position_x',
            'map_position_y',
            'map_size_x',
            'map_size_y',
        ));

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        return ApiResponseController::success($response['stockPlace']->toArray());
    }

    public function updateStockPlace(Request $request, StockPlace $stockPlace)
    {
        $stockPlaceService = new StockPlaceService();
        $stockPlace = $stockPlaceService->updateStockPlace($stockPlace, $request->only(
            'identifier',
            'map_position_x',
            'map_position_y',
            'map_size_x',
            'map_size_y',
            'color',
            'type',
            'is_active',
        ));

        return ApiResponseController::success($stockPlace->toArray());
    }

    public function deleteStockPlace(Request $request, StockPlace $stockPlace)
    {
        $stockPlaceService = new StockPlaceService();
        $response = $stockPlaceService->deleteStockPlace($stockPlace);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        return ApiResponseController::success();
    }

    public function storeStockPlaceCompartment(Request $request, StockPlace $stockPlace)
    {
        $stockPlaceService = new StockPlaceService();

        $compartmentsData = [];

        $templateID = $request->input('template_id');
        if ($templateID) {
            $template = CompartmentsTemplate::find($templateID);
            if (!$template) {
                return ApiResponseController::error('Template not found');
            }

            $templateGroup = ((int) StockPlaceCompartment::where('stock_place_id', $stockPlace->id)->max('template_group')) + 1;

            foreach ($template->data as $templateData) {
                $compartmentsData[] = [
                    'volume_class' => $templateData['volume_class'],
                    'width' => $templateData['width'],
                    'height' => $templateData['height'],
                    'depth' => $templateData['depth'],
                    'is_truck' => $templateData['is_truck'],
                    'is_movable' => $templateData['is_movable'],
                    'is_walk_through' => $templateData['is_walk_through'],
                    'is_manual' => $templateData['is_manual'],
                    'template_id' => $templateID,
                    'template_group' => $templateGroup,
                ];
            }
        }
        else {
            $compartmentsData[] = $request->only(
                'volume_class',
                'width',
                'height',
                'depth',
                'is_truck',
                'is_movable',
                'is_walk_through',
                'is_manual',
            );
        }

        foreach ($compartmentsData as $data) {
            $stockPlaceService->createStockPlaceCompartment($stockPlace, $data);
        }

        return ApiResponseController::success();
    }

    public function updateStockPlaceCompartment(Request $request, StockPlace $stockPlace, StockPlaceCompartment $stockPlaceCompartment)
    {
        $stockPlaceService = new StockPlaceService();
        $stockPlaceCompartment = $stockPlaceService->updateStockPlaceCompartment($stockPlaceCompartment, $request->only(
            'volume_class',
            'width',
            'height',
            'depth',
            'is_truck',
            'is_movable',
            'is_walk_through',
            'is_manual',
            'unleash',
        ));

        $this->pushCompartmentToTemplate($stockPlaceCompartment);

        return ApiResponseController::success($stockPlaceCompartment->toArray());
    }

    public function deleteStockPlaceCompartment(Request $request, StockPlace $stockPlace, StockPlaceCompartment $stockPlaceCompartment)
    {
        if ($stockPlaceCompartment->template_id) {
            return ApiResponseController::error('Cannot delete compartment with template');
        }

        $stockPlaceService = new StockPlaceService();
        $response = $stockPlaceService->deleteStockPlaceCompartment($stockPlaceCompartment);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        return ApiResponseController::success();
    }

    public function getCompartmentTemplates()
    {
        $templates = CompartmentsTemplate::orderBy('name', 'ASC')->get();

        return ApiResponseController::success($templates->toArray());
    }

    public function getStockPlaceGroups()
    {
        $stockPlaceGroups = StockPlaceGroup::all();

        foreach ($stockPlaceGroups as &$stockPlaceGroup) {
            $stockPlaceGroup->stockPlaces = StockPlace::whereIn('id', $stockPlaceGroup->stock_places)->get();
        }

        return ApiResponseController::success($stockPlaceGroups->toArray());
    }

    public function storeStockPlaceGroups(Request $request)
    {
        try {
            $stockPlaceIDs = (string) $request->input('stock_places');
            $stockPlaceIDs = explode(',', $stockPlaceIDs);

            $stockPlaceIDs = array_unique($stockPlaceIDs);
            $stockPlaceIDs = array_filter($stockPlaceIDs);

            foreach ($stockPlaceIDs as $stockPlaceID) {
                $exists = StockPlace::where('id', '=', $stockPlaceID)->exists();
                if (!$exists) {
                    return ApiResponseController::error('Stock place not found');
                }

                $hasGroup = StockPlaceGroup::whereJsonContains('stock_places', $stockPlaceID)->exists();
                if ($hasGroup) {
                    return ApiResponseController::error('Stock place already in group');
                }
            }

            $stockPlaceGroup = StockPlaceGroup::create([
                'stock_places' => $stockPlaceIDs
            ]);
        }
        catch (\Throwable $e) {
            return ApiResponseController::error($e->getMessage());
        }

        return ApiResponseController::success($stockPlaceGroup->toArray());
    }

    public function storeCompartmentSection(Request $request, StockPlace $stockPlace, StockPlaceCompartment $stockPlaceCompartment)
    {
        $compartmentSection = CompartmentSection::create([
            'stock_place_compartment_id' => $stockPlaceCompartment->id
        ]);

        return ApiResponseController::success($compartmentSection->toArray());
    }

    public function deleteCompartmentSection(Request $request, StockPlace $stockPlace, StockPlaceCompartment $stockPlaceCompartment, CompartmentSection $compartmentSection)
    {
        // Remove section from all stock items
        StockItem::where('compartment_section_id', $compartmentSection->id)
            ->update(['compartment_section_id' => 0]);

        $compartmentSection->delete();

        return ApiResponseController::success();
    }

    private function pushCompartmentToTemplate(StockPlaceCompartment $stockPlaceCompartment)
    {
        return;

        if (!$stockPlaceCompartment->template_id) {
            return;
        }

        $template = CompartmentsTemplate::find($stockPlaceCompartment->template_id);
        if (!$template) {
            return;
        }

        $stockPlaceCompartmentIndex = 0;
        $compartments = StockPlaceCompartment::where('stock_place_id', $stockPlaceCompartment->stock_place_id)
            ->where('template_group', $stockPlaceCompartment->template_group)
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($compartments as $index => $compartment) {
            if ($compartment->id == $stockPlaceCompartment->id) {
                $stockPlaceCompartmentIndex = $index;
                break;
            }
        }

        $data = array_reverse($template->data);

        foreach ($template->data as $index => $templateCompartment) {
            if ($index != $stockPlaceCompartmentIndex) {
                continue;
            }

            foreach (CompartmentsTemplate::TEMPLATE_COLUMNS as $column) {
                $data[$index][$column] = $stockPlaceCompartment->{$column};
            }
        }

        $template->update(['data' => array_reverse($data)]);

        $this->pushTemplateToCompartments($template);
    }

    private function pushTemplateToCompartments(CompartmentsTemplate $template)
    {
        return;

        $compartments = StockPlaceCompartment::where('template_id', $template->id)
            ->orderBy('id', 'ASC')
            ->get();

        $groupedCompartments = $compartments->groupBy('stock_place_id');

        foreach($groupedCompartments as $index => $compartments) {
            $groupedCompartments[$index] = $compartments->groupBy('template_group');
        }

        foreach ($groupedCompartments as $stockPlaceCompartments) {
            foreach ($stockPlaceCompartments as $groupCompartments) {
                for ($i = 0;$i < count($groupCompartments);$i++) {
                    $groupCompartment = $groupCompartments[$i];
                    $templateCompartment = $template->data[$i];

                    $updateData = [];
                    foreach (CompartmentsTemplate::TEMPLATE_COLUMNS as $column) {
                        $updateData[$column] = $templateCompartment[$column];
                    }

                    $groupCompartment->update($updateData);
                }
            }
        }
    }
}
