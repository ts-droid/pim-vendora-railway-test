<?php

namespace App\Http\Controllers;

use App\Models\CompartmentSection;
use App\Models\CompartmentsTemplate;
use App\Models\StockItem;
use App\Models\StockItemMovement;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Models\StockPlaceGroup;
use App\Services\WMS\StockPlaceService;
use App\Utilities\WarehouseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function getDetailedStockPlaces(Request $request)
    {
        $stockPlaceIDs = $request->input('stock_place_ids');
        $stockPlaceIDs = explode(',', $stockPlaceIDs);

        $stockPlaces = [];

        foreach ($stockPlaceIDs as $stockPlaceID) {
            $stockPlace = StockPlace::with('compartments', 'compartments.sections')
                ->where('id', $stockPlaceID)
                ->first();

            if (!$stockPlace) {
                continue;
            }

            $stockPlaceArray = $stockPlace->toArray();

            $stockPlaceArray['position_class'] = WarehouseHelper::colorToClass($stockPlaceArray['color']);

            foreach ($stockPlaceArray['compartments'] as &$compartment) {
                $articles = [];

                // Add already placed items
                $articleNumbers = StockItem::where('stock_place_compartment_id', '=', $compartment['id'])
                    ->select('article_number')
                    ->distinct()
                    ->pluck('article_number');

                foreach ($articleNumbers as $articleNumber) {
                    if (!isset($articles[$articleNumber])) {
                        $articles[$articleNumber] = [
                            'article_number' => $articleNumber,
                            'image' => $this->getArticleImage($articleNumber),
                            'stock' => StockItem::where('article_number', '=', $articleNumber)->where('stock_place_compartment_id', '=', $compartment['id'])->count(),
                            'movement' => 0,
                        ];
                    }
                }

                $movementArticleNumbers = StockItemMovement::where('to_stock_place_compartment', '=', $compartment['id'])
                    ->orWhere('from_stock_place_compartment', '=', $compartment['id'])
                    ->select('article_number')
                    ->distinct()
                    ->pluck('article_number');

                foreach ($movementArticleNumbers as $articleNumber) {
                    if (!isset($articles[$articleNumber])) {
                        $articles[$articleNumber] = [
                            'article_number' => $articleNumber,
                            'image' => $this->getArticleImage($articleNumber),
                            'stock' => 0,
                            'movement' => 0,
                        ];
                    }

                    $moveToQuantity = StockItemMovement::where('article_number', '=', $articleNumber)
                        ->where('to_stock_place_compartment', '=', $compartment['id'])
                        ->sum('quantity');

                    $moveFromQuantity = StockItemMovement::where('article_number', '=', $articleNumber)
                        ->where('from_stock_place_compartment', '=', $compartment['id'])
                        ->sum('quantity');

                    $articles[$articleNumber]['movement'] += $moveToQuantity - $moveFromQuantity;
                }


                $compartment['articles'] = $articles;
            }

            $stockPlaces[] = $stockPlaceArray;
        }

        return ApiResponseController::success($stockPlaces);
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
            $stockPlaceGroup->stockPlaces = StockPlace::whereIn('id', $stockPlaceGroup->stock_places)->get()->toArray();
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

    public function updateStockPlaceGroup(Request $request, StockPlaceGroup $stockPlaceGroup)
    {
        $maxVolumeClassSizeA = floatval($request->input('max_volume_class_size_a', 0)) ?: null;
        $maxVolumeClassSizeB = floatval($request->input('max_volume_class_size_b', 0)) ?: null;
        $maxVolumeClassSizeC = floatval($request->input('max_volume_class_size_c', 0)) ?: null;
        $wmsMultiIntelligence = intval($request->input('wms_multi_intelligence', 0)) ?: null;
        $wmsMultiIntelligencePeriod = intval($request->input('wms_multi_intelligence_period', 0)) ?: null;

        $stockPlaceGroup->update([
            'max_volume_class_size_a' => $maxVolumeClassSizeA,
            'max_volume_class_size_b' => $maxVolumeClassSizeB,
            'max_volume_class_size_c' => $maxVolumeClassSizeC,
            'wms_multi_intelligence' => $wmsMultiIntelligence,
            'wms_multi_intelligence_period' => $wmsMultiIntelligencePeriod,
        ]);

        return ApiResponseController::success($stockPlaceGroup->toArray());
    }

    public function deleteStockPlaceGroup(StockPlaceGroup $stockPlaceGroup)
    {
        $stockPlaceGroup->delete();

        return ApiResponseController::success();
    }

    public function storeCompartmentSection(Request $request, StockPlace $stockPlace, StockPlaceCompartment $stockPlaceCompartment)
    {
        // Make sure the compartment is empty
        $hasItems = StockItem::where('stock_place_compartment_id', $stockPlaceCompartment->id)->exists();
        if ($hasItems) {
            return ApiResponseController::error('Compartment must be empty to be able to edit sections');
        }

        $compartmentSection = CompartmentSection::create([
            'stock_place_compartment_id' => $stockPlaceCompartment->id
        ]);

        return ApiResponseController::success($compartmentSection->toArray());
    }

    public function deleteCompartmentSection(Request $request, StockPlace $stockPlace, StockPlaceCompartment $stockPlaceCompartment, CompartmentSection $compartmentSection)
    {
        // Make sure the compartment is empty
        $hasItems = StockItem::where('stock_place_compartment_id', $stockPlaceCompartment->id)->exists();
        if ($hasItems) {
            return ApiResponseController::error('Compartment must be empty to be able to edit sections');
        }

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

    private function getArticleImage(string $articleNumber)
    {
        $articleID = DB::table('articles')
            ->select('id')
            ->where('article_number', '=', $articleNumber)
            ->pluck('id')
            ->first();

        return DB::table('article_images')
            ->select('path_url')
            ->where('article_id', '=', $articleID)
            ->where('solid_background', '=', 1)
            ->orderBy('list_order', 'ASC')
            ->pluck('path_url')
            ->first();
    }
}
