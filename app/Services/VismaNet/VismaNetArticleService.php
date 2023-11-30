<?php

namespace App\Services\VismaNet;

use App\Models\Article;

class VismaNetArticleService extends VismaNetApiService
{

    public function updateArticle(Article $article)
    {
        $data = [
            'attributeLines' => [
                [
                    'attributeId' => ['value' => 'AFPI'],
                    'attributeValue' => ['value' => (int) $article->inner_box],
                ],
                [
                    'attributeId' => ['value' => 'ANTINKART'],
                    'attributeValue' => ['value' => (int) $article->master_box],
                ],
            ]
        ];

        $response = $this->callAPI('PUT', '/v1/inventory/' . $article->article_number, $data);
    }

}
