<?php

namespace App\Services\Todo;

use App\Enums\TodoQueue;
use App\Enums\TodoType;
use App\Models\TodoItem;
use App\Models\User;
use Illuminate\Http\Request;

class TodoService
{
    public function getQueueCount(TodoQueue $queue): int
    {
        return (int) TodoItem::where('queue', $queue)
            ->whereNull('reserved_at')
            ->count();
    }

    public function getNextQueueItem(TodoQueue $queue)
    {
        return TodoItem::where('queue', $queue)
            ->whereNull('reserved_at')
            ->whereNull('completed_at')
            ->orderBy('list_order', 'ASC')
            ->first();
    }

    public function getQueueItems(TodoQueue $queue, int $limit = 0)
    {
        $query = TodoItem::where('queue', $queue)
            ->whereNull('reserved_at')
            ->whereNull('completed_at')
            ->orderBy('list_order', 'ASC');

        if ($limit) {
            $query->limit($limit);
        }

        $todoItems = $query->get();

        if ($todoItems) {
            $todoItemMetaService = new TodoItemMetaService();

            foreach ($todoItems as &$todoItem) {
                $todoItem->meta_data = $todoItemMetaService->getMeta($todoItem->type, $todoItem->data);
            }
        }

        return $todoItems;
    }

    public function getItem(int $itemID): ?array
    {
        $item = TodoItem::where('id', $itemID)->first();
        if (!$item) {
            return null;
        }

        $todoItemMetaService = new TodoItemMetaService();
        $metaData = $todoItemMetaService->getMeta($item->type, $item->data);

        return [
            'item' => $item->toArray(),
            'meta_data' => $metaData,
        ];
    }

    public function reserveItem(TodoItem $todoItem, int $reservedBy): array
    {
        // Check if this item is already reserved
        $isReserved = TodoItem::where('id', $todoItem->id)
            ->whereNotNull('reserved_at')
            ->exists();

        if ($isReserved) {
            return [
                'success' => false,
                'error' => 'Item already reserved',
            ];
        }

        // Reserve the item
        $reserved = $todoItem->update([
            'reserved_by' => $reservedBy,
            'reserved_at' => date('Y-m-d H:i:s')
        ]);

        if (!$reserved) {
            return [
                'success' => false,
                'error' => 'Failed to reserve item',
            ];
        }

        return [
            'success' => true,
            'error' => '',
        ];
    }

    public function unreserveItem(TodoItem $todoItem)
    {
        if ($todoItem->completed_at) {
            return [
                'success' => false,
                'error' => 'Item already completed',
            ];
        }

        if ($todoItem->source === 'tmp') {
            $todoItem->delete();
        }
        else {
            $todoItem->update([
                'reserved_by' => 0,
                'reserved_at' => null
            ]);
        }

        return [
            'success' => true,
            'error' => '',
        ];
    }

    public function unreserveOldItems(int $thresholdMinutes = 30)
    {
        $todoItems = TodoItem::whereNull('completed_at')
            ->where('reserved_at', '<', date('Y-m-d H:i:s', strtotime('-' . $thresholdMinutes . ' minutes')))
            ->orderBy('reserved_at', 'DESC')
            ->get();

        if (!$todoItems) {
            return;
        }

        foreach ($todoItems as $todoItem) {
            $this->unreserveItem($todoItem);
        }
    }

    protected function createItem(TodoQueue $queue, TodoType $type, string $title, string $description, array $data, int $createdBy, string $source): TodoItem
    {
        $currentListOrder = (int) TodoItem::where('queue', $queue)->max('list_order');

        return TodoItem::create([
            'queue' => $queue,
            'type' => $type,
            'list_order' => $currentListOrder + 1,
            'title' => $title,
            'description' => $description,
            'data' => $data,
            'created_by' => $createdBy,
            'source' => $source
        ]);
    }

    public function submitItem(TodoItem $todoItem, Request|array $data): array
    {
        $response = [
            'success' => false,
            'error' => 'Unknown error',
        ];

        switch ($todoItem->type) {
            case TodoType::CollectArticle:
                $service = new TodoItemService();
                $response = $service->submitCollectArticle($todoItem, $data);
                break;
        }

        if ($response['success']) {
            $todoItem->update(['completed_at' => date('Y-m-d H:i:s')]);
        }

        return $response;
    }

    public function deleteTmpItems()
    {
        TodoItem::where('source', 'tmp')
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->delete();
    }
}
