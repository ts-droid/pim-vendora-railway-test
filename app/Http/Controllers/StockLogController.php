<?php

namespace App\Http\Controllers;

use App\Models\StockLog;
use Illuminate\Http\Request;

class StockLogController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(StockLog::class, $request);

        $query = $this->getQueryWithFilter(StockLog::class, $filter);

        $stockLogs = $query->orderBy('created_at', 'DESC')->get();

        return ApiResponseController::success($stockLogs->toArray());
    }

    public function logStock(string $articleNumber, int $stock): void
    {
        // Do not log if the stock is the same
        if ($stock == $this->getCurrentStock($articleNumber)) {
            return;
        }

        StockLog::create([
            'article_number' => $articleNumber,
            'stock' => $stock,
        ]);
    }

    public function getCurrentStock(string $articleNumber): int
    {
        $stockLog = StockLog::where('article_number', $articleNumber)
            ->orderBy('created_at', 'desc')
            ->first();

        return (int) ($stockLog->stock ?? 0);
    }

    public function getStockByDate(string $articleNumber, string $date): int
    {
        $date = date('Y-m-d 23:59:59', strtotime($date));

        $stockLog = StockLog::where('article_number', $articleNumber)
            ->where('created_at', '<=', $date)
            ->orderBy('created_at', 'desc')
            ->first();

        return (int) ($stockLog->stock ?? 0);
    }

    public function getAverageStock(string $articleNumber, string $startDate, string $endDate): int
    {
        $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
        $endDate = date('Y-m-d 23:59:59', strtotime($endDate));

        $stockLogs = StockLog::where('article_number', $articleNumber)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->orderBy('created_at', 'desc')
            ->get();

        if (!$stockLogs) {
            return 0;
        }

        $stockSum = 0;
        foreach ($stockLogs as $stockLog) {
            $stockSum += $stockLog->stock;
        }

        return (int) ($stockSum / count($stockLogs));
    }
}
