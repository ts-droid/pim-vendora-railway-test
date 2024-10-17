<?php

namespace App\Http\Controllers\Api;

use App\Enums\TodoQueue;
use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\TodoItem;
use App\Services\Todo\TodoItemService;
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

        $page = (int) $request->input('page', 1);

        $todoService = new TodoService();
        $todoItems = $todoService->getQueueItems($queue, 50, $page);

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

    public function createItemCollectArticle(Request $request)
    {
        $input = $request->input('input') ?: $request->input('ean');
        $variant = $request->input('variant', 'custom');

        if (!$input) {
            return ApiResponseController::error('Input is required');
        }

        $article = Article::where('ean', $input)
            ->orWhere('article_number', $input)
            ->first();

        if (!$article) {
            return ApiResponseController::error('Article not found');
        }

        $todoItemService = new TodoItemService();
        $todoItem = $todoItemService->createCollectArticle($article->id, $variant, 0, 'tmp');

        $response = $todoItemService->reserveItem($todoItem, 0);
        if (!$response['success']) {
            return ApiResponseController::error($response['error']);
        }

        return ApiResponseController::success($todoItemService->getItem($todoItem->id));
    }

    public function getItem(Request $request, string $queue, int $item)
    {
        $todoService = new TodoService();
        $todoItem = $todoService->getItem($item);

        if (!$todoItem) {
            return ApiResponseController::error('Item not found');
        }

        return ApiResponseController::success($todoItem);
    }

    public function submitItem(Request $request, string $queue, int $item)
    {
        $todoItem = TodoItem::where('id', $item)->first();
        if (!$todoItem) {
            return ApiResponseController::error('Item not found');
        }

        if ($todoItem->completed_at) {
            return ApiResponseController::error('Item already completed');
        }

        try {
            $todoService = new TodoService();
            $submitResponse = $todoService->submitItem($todoItem, $request);
        }
        catch (\Throwable $e) {
            return ApiResponseController::error($e->getMessage());
        }

        if (!$submitResponse['success']) {
            return ApiResponseController::error($submitResponse['error']);
        }

        return ApiResponseController::success();
    }

    public function reserveItem(Request $request, string $queue, int $item)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $todoItem = TodoItem::where('id', $item)->first();
        if (!$todoItem) {
            return ApiResponseController::error('Item not found');
        }

        $todoService = new TodoService();

        $response = $todoService->reserveItem($todoItem, intval($request->user_id));
        if (!$response['success']) {
            return ApiResponseController::error($response['error']);
        }

        return ApiResponseController::success($todoService->getItem($item));
    }

    public function unreserveItem(Request $request, string $queue, int $item)
    {
        $todoItem = TodoItem::where('id', $item)->first();
        if (!$todoItem) {
            return ApiResponseController::error('Item not found');
        }

        if ($todoItem->completed_at) {
            return ApiResponseController::error('Item already completed');
        }

        $todoService = new TodoService();

        $response = $todoService->unreserveItem($todoItem);
        if (!$response['success']) {
            return ApiResponseController::error($response['error']);
        }

        return ApiResponseController::success([]);
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
