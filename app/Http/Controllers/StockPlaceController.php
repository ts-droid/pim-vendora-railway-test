<?php

namespace App\Http\Controllers;

use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Models\StockPlaceTemplate;
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

    public function getStockPlaceTemplates()
    {
        $stockPlaceTemplates = StockPlaceTemplate::orderBy('name', 'ASC')->get();

        return ApiResponseController::success($stockPlaceTemplates->toArray());
    }

    public function storeStockPlaceTemplate(Request $request, StockPlaceTemplate $stockPlaceTemplate)
    {
        $identifier = $request->input('identifier');
        $mapPositionX = $request->input('map_position_x');
        $mapPositionY = $request->input('map_position_y');

        if (!$identifier) {
            return ApiResponseController::error('Identifier is required');
        }
        if (!$mapPositionX) {
            return ApiResponseController::error('X position is required');
        }
        if (!$mapPositionY) {
            return ApiResponseController::error('Y position is required');
        }

        $stockPlaceService = new StockPlaceService();
        $response = $stockPlaceService->copyStockPlaceTemplate(
            $stockPlaceTemplate,
            $identifier,
            $mapPositionX,
            $mapPositionY
        );

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
        ));

        return ApiResponseController::success($stockPlace->toArray());
    }

    public function createStockPlaceTemplate(Request $request, StockPlace $stockPlace)
    {
        $name = $request->input('name');
        if (!$name) {
            return ApiResponseController::error('Name is required');
        }

        $stockPlaceService = new StockPlaceService();
        $stockPlaceService->createStockPlaceTemplate($stockPlace, $name);

        return ApiResponseController::success();
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
        $response = $stockPlaceService->createStockPlaceCompartment($stockPlace, $request->only(
            'volume_class',
            'width',
            'height',
            'depth',
            'is_truck',
            'is_movable',
            'is_walk_through',
            'is_manual',
        ));

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        return ApiResponseController::success($response['stockPlaceCompartment']->toArray());
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
}
