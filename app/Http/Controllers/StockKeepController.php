<?php

namespace App\Http\Controllers;

use App\Models\StockKeepTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockKeepController extends Controller
{
    public function get(Request $request)
    {
        $page = $request->input('page', 1);
        $pageSize = $request->input('page_size', 50);

        $status = $request->input('status', '');
        $date = $request->input('date', null);
        $archived = (int) $request->input('archived', 0);

        $query = StockKeepTransaction::where('status', '=', $status)
            ->where('is_archived', '=', $archived);

        if ($date) {
            $query->where('created_at', 'LIKE', $date . '%');
        }

        $transactions = $query->orderBy('created_at', 'DESC')
            ->limit($pageSize)
            ->offset(($page - 1) * $pageSize)
            ->get();

        return ApiResponseController::success([
            'results' => $transactions->toArray(),
            'page' => $page,
            'next_page' => ($transactions->count() == $pageSize) ? $page + 1 : null,
        ]);
    }

    public function archivedDates()
    {
        $dates = DB::table('stock_keep_transactions')
            ->select(DB::raw('DATE(created_at) as date'))
            ->where('is_archived', '=', 1)
            ->groupBy('date')
            ->pluck('date');

        return ApiResponseController::success($dates->toArray());
    }

    public function archive(Request $request)
    {
        $ids = $request->input('ids');
        $ids = explode(',', $ids);

        StockKeepTransaction::whereIn('id', $ids)
            ->update(['is_archived' => 1]);

        return ApiResponseController::success();
    }
}
