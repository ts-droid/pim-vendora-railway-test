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
            ->orderBy('list_order', 'ASC')
            ->first();
    }

    public function getQueueItems(TodoQueue $queue)
    {
        return TodoItem::where('queue', $queue)
            ->whereNull('reserved_at')
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

    public function reserveItem(TodoItem $todoItem, int $reservedBy): bool
    {
        // Check if this item is already reserved
        $isReserved = TodoItem::where('id', $todoItem->id)
            ->whereNotNull('reserved_at')
            ->exists();

        if ($isReserved) {
            return false;
        }

        // Reserve the item
        return $todoItem->update([
            'reserved_by' => $reservedBy,
            'reserved_at' => date('Y-m-d H:i:s')
        ]);
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
}
