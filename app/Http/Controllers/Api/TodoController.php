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
    public function reserveNext(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'queue' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $queue = $this->getQueueEnum($request->queue);
        if ($queue === null) {
            return ApiResponseController::error('Invalid queue');
        }

        $todoService = new TodoService();
        $todoItem = $todoService->getNextQueueItem($queue);

        if (!$todoItem) {
            return ApiResponseController::error('No items in queue');
        }

        return ApiResponseController::success($todoItem->toArray());
    }

    public function getQueueCount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'queue' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $queue = $this->getQueueEnum($request->queue);
        if ($queue === null) {
            return ApiResponseController::error('Invalid queue');
        }

        $todoService = new TodoService();

        return ApiResponseController::success([
            'count' => $todoService->getQueueCount($queue)
        ]);
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
