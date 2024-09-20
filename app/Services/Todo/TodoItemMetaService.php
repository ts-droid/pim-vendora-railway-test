<?php

namespace App\Services\Todo;

use App\Enums\TodoType;
use App\Models\Article;
use App\Models\ArticleImage;

class TodoItemMetaService
{
    public function getMeta(TodoType $type, array $data): array
    {
        switch ($type) {
            case TodoType::CollectArticleWeight:
                return $this->getCollectArticleWeightMeta($data);
        }

        return [];
    }

    private function getCollectArticleWeightMeta(array $data): array
    {
        $article = Article::where('id', ($data['article_id'] ?? 0))->first();

        $image = ArticleImage::select('path_url')
            ->where('article_id', $article->id)
            ->orderBy('list_order', 'ASC')
            ->limit(1)
            ->first();

        $form = [
            [
                'key' => 'weight',
                'type' => 'number',
                'label' => 'Weight (g)',
                'placeholder' => 'Enter weight',
            ]
        ];

        return [
            'article' => $article ? $article->toArray() : null,
            'image' => $image ? $image->path_url : null,
            'form' => $form,
        ];
    }
}
