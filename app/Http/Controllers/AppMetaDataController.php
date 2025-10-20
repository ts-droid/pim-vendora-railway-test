<?php

namespace App\Http\Controllers;

use App\Enums\TodoQueue;
use App\Models\Shipment;
use App\Models\StockItemMovement;
use App\Models\StockKeepTodo;
use App\Services\Todo\TodoService;
use Illuminate\Http\Request;

class AppMetaDataController extends Controller
{
    public function getVersion()
    {
        $version = ConfigController::getConfig('app_latest_version');
        $buildNumber = ConfigController::getConfig('app_latest_build_number');

        return ApiResponseController::success([
            'version' => $version,
            'build_number' => $buildNumber
        ]);
    }

    public function getTabCounts()
    {
        $counts = [
            'picking' => 0,
            'todo' => 0,
            'delivery' => 0,
            'inventory' => 0,
            'warehouse' => 0,
        ];

        // Picking
        $counts['picking'] = (int) Shipment::where('status', 'Open')
            ->where('operation', 'Issue')
            ->where('internal_status', 0)
            ->count();

        // TODO's
        $queue = $this->getQueueEnum('wms');
        if ($queue) {
            $todoService = new TodoService();
            $counts['todo'] = $todoService->getQueueCount($queue);
        }

        // TODO: Add delivery counts
        $counts['delivery'] = 0;

        // Invenstory
        $counts['inventory'] = (int) StockKeepTodo::count();

        // Warehouse
        $counts['warehouse'] = (int) StockItemMovement::where('is_investigation', 0)->count();

        return ApiResponseController::success($counts);
    }

    private function getQueueEnum(string $string)
    {
        try {
            return TodoQueue::from($string);
        } catch (\Exception $e) {
            return null;
        }
    }
}
