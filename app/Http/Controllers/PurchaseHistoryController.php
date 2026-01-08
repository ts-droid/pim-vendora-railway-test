<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseHistoryController extends Controller
{
    public function index(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleNumber = (string) $request->input('article_number');

        $orderLines = DB::table('purchase_order_lines')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->select(
                'purchase_orders.order_number',
                'purchase_orders.date',
                DB::raw('SUM(purchase_order_lines.quantity) AS quantity'),
                DB::raw('AVG(purchase_order_lines.unit_cost) AS average_unit_cost')
            )
            ->where('article_number', $articleNumber)
            ->groupBy('purchase_order_id')
            ->orderBy('purchase_orders.date', 'DESC')
            ->get();

        return ApiResponseController::success([
            'history' => $orderLines->toArray()
        ]);
    }
}
