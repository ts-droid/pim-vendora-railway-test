<?php

namespace App\Services\Todo;

use App\Enums\TodoQueue;
use App\Enums\TodoType;
use App\Models\Article;
use App\Models\TodoItem;
use Illuminate\Support\Facades\DB;

class TodoWmsService extends TodoService
{
    private TodoQueue $queue = TodoQueue::WMS;

    public function createCollectArticleWeight(int $articleID, int $createdBy): TodoItem
    {
        $article = DB::table('articles')->select('article_number')->where('id', $articleID)->first();
        $articleNumber = $article->article_number ?? '';

        return $this->createItem(
            $this->queue,
            TodoType::CollectArticleWeight,
            'Collect article weight',
            $articleNumber,
            ['article_id' => $articleID],
            $createdBy
        );
    }

    public function submitCollectArticleWeight(TodoItem $todoItem, array $data): array
    {
        $articleID = $todoItem->data['article_id'] ?? 0;
        $weight = (int) ($data['weight'] ?? 0);

        if (!$weight) {
            return [
                'success' => false,
                'error' => 'Weight is required',
            ];
        }

        //Article::where('id', $articleID)->update(['weight' => $weight]);

        return [
            'success' => true,
            'error' => '',
        ];
    }
}
