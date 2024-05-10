<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseHistoryController extends Controller
{
    public function index(Request $request)
    {
        $articleNumber = (string) $request->input('article_number');

        $orderLines = DB::table('purchase_order_lines')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->select('purchase_orders.order_number', 'purchase_orders.date', DB::raw('SUM(quantity) AS quantity'))
            ->where('article_number', $articleNumber)
            ->groupBy('purchase_order_id')
            ->orderBy('purchase_orders.date', 'DESC')
            ->get();

        return ApiResponseController::success([
            'history' => $orderLines->toArray()
        ]);
    }
}
