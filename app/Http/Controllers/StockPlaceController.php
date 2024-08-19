<?php

namespace App\Http\Controllers;

use App\Models\StockPlace;
use Illuminate\Http\Request;

class StockPlaceController extends Controller
{
    public function getAll(Request $request)
    {
        $stockPlaces = StockPlace::with('compartments')->all();

        return ApiResponseController::success($stockPlaces->toArray());
    }
}
