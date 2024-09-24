<?php

namespace App\Services\Todo;

use App\Enums\TodoType;
use App\Models\Article;
use App\Models\ArticleImage;
use Illuminate\Support\Facades\DB;

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
        $article = DB::table('articles')
            ->select('id', 'weight')
            ->where('id', ($data['article_id'] ?? 0))
            ->first();

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
                'value' => $article ? $article->weight : 0,
            ]
        ];

        return [
            'image' => $image ? $image->path_url : null,
            'form' => $form,
        ];
    }
}
