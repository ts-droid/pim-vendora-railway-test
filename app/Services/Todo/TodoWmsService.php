<?php

namespace App\Services\Todo;

use App\Enums\TodoQueue;
use App\Enums\TodoType;
use App\Models\TodoItem;

class TodoWmsService extends TodoService
{
    private TodoQueue $queue = TodoQueue::WMS;

    public function createCollectArticleMeasurements(int $articleId, int $createdBy): TodoItem
    {
        return $this->createItem(
            $this->queue,
            TodoType::CollectArticleMeasurements,
            'Collect article measurements',
            'Collect measurements for article',
            ['article_id' => $articleId],
            $createdBy
        );
    }
}
