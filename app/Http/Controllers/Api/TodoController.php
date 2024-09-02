<?php

namespace App\Http\Controllers\Api;

use App\Enums\TodoQueue;
use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Services\Todo\TodoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TodoController extends Controller
{
    public function getQueues()
    {
        $queues = array_map(fn($case) => $case->value, TodoQueue::cases());

        return ApiResponseController::success($queues);
    }

    public function getQueue(Request $request, string $queue)
    {
        $queue = $this->getQueueEnum($queue);
        if ($queue === null) {
            return ApiResponseController::error('Invalid queue');
        }

        $todoService = new TodoService();
        $todoItems = $todoService->getQueueItems($queue);

        return ApiResponseController::success($todoItems->toArray());
    }

    public function getQueueCount(Request $request, string $queue)
    {
        $queue = $this->getQueueEnum($queue);
        if ($queue === null) {
            return ApiResponseController::error('Invalid queue');
        }

        $todoService = new TodoService();

        return ApiResponseController::success([
            'count' => $todoService->getQueueCount($queue)
        ]);
    }

    public function reserveQueue(Request $request, string $queue)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $queue = $this->getQueueEnum($queue);
        if ($queue === null) {
            return ApiResponseController::error('Invalid queue');
        }

        $todoService = new TodoService();
        $todoItem = $todoService->getNextQueueItem($queue);

        if (!$todoItem) {
            return ApiResponseController::error('No items in queue');
        }

        $reserved = $todoService->reserveItem($todoItem, intval($request->user_id));
        if (!$reserved) {
            return ApiResponseController::error('Failed to reserve item.');
        }

        return ApiResponseController::success($todoItem->toArray());
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
