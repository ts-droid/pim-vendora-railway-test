<?php

namespace App\Services\Todo;

use App\Enums\TodoQueue;
use App\Enums\TodoType;
use App\Models\TodoItem;

class TodoService
{
    public function getQueueCount(TodoQueue $queue): int
    {
        return (int) TodoItem::where('queue', $queue)
            ->where('reserved_by', 0)
            ->count();
    }

    public function getNextQueueItem(TodoQueue $queue)
    {
        return TodoItem::where('queue', $queue)
            ->where('reserved_by', 0)
            ->orderBy('list_order', 'ASC')
            ->first();
    }

    public function getQueueItems(TodoQueue $queue)
    {
        return TodoItem::where('queue', $queue)
            ->where('reserved_by', 0)
            ->orderBy('list_order', 'ASC')
            ->get();
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
