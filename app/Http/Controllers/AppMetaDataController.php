<?php

namespace App\Http\Controllers;

use App\Enums\TodoQueue;
use App\Services\Todo\TodoService;
use Illuminate\Http\Request;

class AppMetaDataController extends Controller
{
    public function getTabCounts()
    {
        $counts = [
            'picking' => 0,
            'todo' => 0,
            'inventory' => 0,
            'warehouse' => 0,
        ];

        $queue = $this->getQueueEnum('wms');
        if ($queue) {
            $todoService = new TodoService();
            $counts['todo'] = $todoService->getQueueCount($queue);
        }


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
