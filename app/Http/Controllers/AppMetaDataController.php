<?php

namespace App\Http\Controllers;

use App\Enums\TodoQueue;
use App\Models\PurchaseOrderShipment;
use App\Models\Shipment;
use App\Models\StockItemMovement;
use App\Models\StockKeepTodo;
use App\Services\Todo\TodoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppMetaDataController extends Controller
{
    public function getVersion()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $version = ConfigController::getConfig('app_latest_version');
        $buildNumber = ConfigController::getConfig('app_latest_build_number');

        return ApiResponseController::success([
            'version' => $version,
            'build_number' => $buildNumber
        ]);
    }

    public function getTabCounts()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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

        // Delivery counts
        $counts['delivery'] = (int) PurchaseOrderShipment::where('is_completed', 0)->count();

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
