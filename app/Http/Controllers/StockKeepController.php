<?php

namespace App\Http\Controllers;

use App\Models\StockKeepTransaction;
use Illuminate\Http\Request;

class StockKeepController extends Controller
{
    public function get(Request $request)
    {
        $page = $request->input('page', 1);
        $pageSize = $request->input('page_size', 50);

        $status = $request->input('status', '');

        $transactions = StockKeepTransaction::where('status', '=', $status)
            ->orderBy('created_at', 'DESC')
            ->limit($pageSize)
            ->offset(($page - 1) * $pageSize)
            ->get();

        return ApiResponseController::success([
            'results' => $transactions->toArray(),
            'page' => $page,
            'next_page' => ($transactions->count() == $pageSize) ? $page + 1 : null,
        ]);
    }
}
