<?php

namespace App\Http\Controllers;

use App\Models\StockItemMovement;
use Illuminate\Http\Request;

class AppWarehouseController extends Controller
{
    public function getMovements()
    {
        $stockItemMovements = StockItemMovement::with('fromStockPlaceCompartment', 'toStockPlaceCompartment')
            ->orderBy('id', 'ASC')
            ->get();

        return ApiResponseController::success($stockItemMovements->toArray());
    }
}
