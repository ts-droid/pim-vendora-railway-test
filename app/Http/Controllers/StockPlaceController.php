<?php

namespace App\Http\Controllers;

use App\Models\CompartmentsTemplate;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Services\WMS\StockPlaceService;
use Illuminate\Http\Request;

class StockPlaceController extends Controller
{
    public function getStockPlaces(Request $request)
    {
        $stockPlaces = StockPlace::with('compartments')->get();

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
        $stockPlace->load('compartments');

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
        ));

        return ApiResponseController::success($stockPlaceCompartment->toArray());
    }

    public function deleteStockPlaceCompartment(Request $request, StockPlace $stockPlace, StockPlaceCompartment $stockPlaceCompartment)
    {
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
}
