<?php

namespace App\Http\Controllers;

use App\Models\StockKeepTransaction;
use Illuminate\Http\Request;

class StockKeepController extends Controller
{
    public function get(Request $request)
    {
        $status = $request->input('status', '');
        $date = $request->input('date', '') ?: date('Y-m-d');

        $transactions = StockKeepTransaction::where('status', $status)
            ->whereDate('created_at', $date)
            ->orderBy('created_at', 'DESC')
            ->get();

        return ApiResponseController::success($transactions->toArray());
    }
}
