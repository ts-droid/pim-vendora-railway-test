<?php

namespace App\Http\Controllers;

use App\Models\StockItemMovement;
use App\Services\WMS\StockItemService;
use Illuminate\Http\Request;

class AppWarehouseController extends Controller
{
    public function getMovements()
    {
        $stockItemMovements = StockItemMovement::with(
                'fromStockPlaceCompartment',
                'fromStockPlaceCompartment.stockPlace',
                'toStockPlaceCompartment',
                'toStockPlaceCompartment.stockPlace'
            )
            ->orderBy('id', 'ASC')
            ->get();

        return ApiResponseController::success($stockItemMovements->toArray());
    }

    public function getMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemMovement = StockItemMovement::with(
                'fromStockPlaceCompartment',
                'fromStockPlaceCompartment.stockPlace',
                'toStockPlaceCompartment',
                'toStockPlaceCompartment.stockPlace'
            )
            ->where('id', $stockItemMovement->id)
            ->first();

        return ApiResponseController::success($stockItemMovement->toArray());
    }

    public function confirmMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemService = new StockItemService();

        $stockItemMovement->load('toStockPlaceCompartment', 'fromStockPlaceCompartment');

        if ($stockItemMovement->from_stock_place_compartment) {
            // Move the item from the existing compartment to the new compartment
            $response = $stockItemService->moveStockItems(
                $stockItemMovement->article_number,
                $stockItemMovement->fromStockPlaceCompartment,
                $stockItemMovement->toStockPlaceCompartment,
                $stockItemMovement->quantity
            );
        }
        else {
            // Insert the item to the new compartment
            $response = $stockItemService->addStockItem(
                $stockItemMovement->article_number,
                $stockItemMovement->toStockPlaceCompartment,
                $stockItemMovement->quantity
            );
        }

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        $stockItemMovement->delete();

        return ApiResponseController::success();
    }

    public function pingMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemMovement->update(['ping_at' => time()]);

        return ApiResponseController::success();
    }

    public function unpingMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemMovement->update(['ping_at' => 0]);

        return ApiResponseController::success();
    }
}
