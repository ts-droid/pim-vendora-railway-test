<?php

namespace App\Services\Todo;

use App\Enums\TodoQueue;
use App\Enums\TodoType;
use App\Models\TodoItem;
use App\Models\User;

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

    public function getQueueItems(TodoQueue $queue)
    {
        return TodoItem::where('queue', $queue)
            ->whereNull('reserved_at')
            ->whereNull('completed_at')
            ->orderBy('list_order', 'ASC')
            ->get();
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

    protected function createItem(TodoQueue $queue, TodoType $type, string $title, string $description, array $data, int $createdBy): TodoItem
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
        ]);
    }

    public function submitItem(TodoItem $todoItem, array $data): array
    {
        $response = [
            'success' => false,
            'error' => 'Unknown error',
        ];

        switch ($todoItem->type) {
            case TodoType::CollectArticleWeight:
                $service = new TodoWmsService();
                $response = $service->submitCollectArticleWeight($todoItem, $data);
                break;
        }

        if ($response['success']) {
            $todoItem->update(['completed_at' => date('Y-m-d H:i:s')]);
        }

        return $response;
    }
}
