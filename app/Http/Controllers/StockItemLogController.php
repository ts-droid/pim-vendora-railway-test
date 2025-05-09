<?php

namespace App\Http\Controllers;

use App\Models\StockItemLog;
use Illuminate\Http\Request;

class StockItemLogController extends Controller
{
    public function index(Request $request)
    {
        $stockLogs = [];

        $articleNumber = $request->input('article_number');

        if ($articleNumber) {
            $stockLogs = StockItemLog::where('article_number', $articleNumber)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('stockItemLogs.index', compact('stockLogs'));
    }
}
