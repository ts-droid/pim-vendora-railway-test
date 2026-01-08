<?php

namespace App\Http\Controllers;

use App\Models\StockItemLog;
use App\Services\WMS\StockPlaceService;
use Illuminate\Http\Request;

class StockItemLogController extends Controller
{
    public function index(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $stockLogs = [];

        $articleNumber = $request->input('article_number');
        $identifier = $request->input('identifier');

        if ($articleNumber) {
            $stockLogsQuery = StockItemLog::where('article_number', $articleNumber);

            if ($identifier) {
                $stockPlaceService = new StockPlaceService();
                $stockPlaceCompartment = $stockPlaceService->getCompartmentByIdentifier($identifier);

                if ($stockPlaceCompartment) {
                    $stockLogsQuery->where('stock_place_compartment_id', $stockPlaceCompartment->id);
                }
            }

            $stockLogsQuery->orderBy('created_at', 'desc')->orderBy('id', 'DESC');

            $stockLogs = $stockLogsQuery->get();
        }

        $sumQuantity = 0;
        if ($stockLogs) {
            $sumQuantity = $stockLogs->sum('quantity');
        }

        return view('stockItemLogs.index', compact('stockLogs', 'sumQuantity'));
    }
}
