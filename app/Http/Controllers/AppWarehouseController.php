<?php

namespace App\Http\Controllers;

use App\Models\StockItemMovement;
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

    public function confirmMovement(StockItemMovement $stockItemMovement)
    {
        return ApiResponseController::success();
    }

    public function pingMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemMovement->update(['ping_at' => now()]);

        return ApiResponseController::success();
    }

    public function unpingMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemMovement->update(['ping_at' => 0]);

        return ApiResponseController::success();
    }
}
