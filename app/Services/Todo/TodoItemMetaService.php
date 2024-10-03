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
            case TodoType::CollectArticle:
                return $this->getCollectArticleMeta($data);
        }

        return [];
    }

    private function getCollectArticleMeta(array $data): array
    {
        $article = DB::table('articles')
            ->select('*')
            ->where('id', ($data['article_id'] ?? 0))
            ->first();

        $image = ArticleImage::select('path_url')
            ->where('article_id', $article->id)
            ->orderBy('list_order', 'ASC')
            ->limit(1)
            ->first();


        return [
            'article_number' => $article->article_number,
            'ean' => $article->ean,
            'description' => $article->description,
            'width' => $article->width,
            'height' => $article->height,
            'depth' => $article->depth,
            'weight' => $article->weight,
            'inner_box' => $article->inner_box,
            'master_box' => $article->master_box,
            'image' => $image ? $image->path_url : null,
            'package_image_front' => $article->package_image_front,
            'package_image_front_url' => $article->package_image_front_url,
            'package_image_back' => $article->package_image_back,
            'package_image_back_url' => $article->package_image_back_url,
        ];
    }
}
