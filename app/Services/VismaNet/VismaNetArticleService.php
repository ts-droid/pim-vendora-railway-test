<?php

namespace App\Services\VismaNet;

use App\Models\Article;

class VismaNetArticleService extends VismaNetApiService
{

    public function updateArticle(Article $article): array
    {
        $data = [
            'status' => ['value' => $article->status],
            'type' => ['value' => $article->article_type],
            'description' => ['value' => $article->description],
            'intrastat' => [
                'cN8' => ['value' => $article->hs_code],
                'countryOfOrigin' => ['value' => $article->origin_country],
            ],
            'packaging' => [
                'baseItemWeight' => ['value' => $article->weight]
            ],
            'attributeLines' => [
                [
                    'attributeId' => ['value' => 'AFPI'],
                    'attributeValue' => ['value' => (int) $article->inner_box],
                ],
                [
                    'attributeId' => ['value' => 'ANTINKART'],
                    'attributeValue' => ['value' => (int) $article->master_box],
                ],
                [
                    'attributeId' => ['value' => 'STRLFRPB'],
                    'attributeValue' => ['value' => (int) $article->width],
                ],
                [
                    'attributeId' => ['value' => 'STRLFRPH'],
                    'attributeValue' => ['value' => (int) $article->height],
                ],
                [
                    'attributeId' => ['value' => 'STRLFRPD'],
                    'attributeValue' => ['value' => (int) $article->depth],
                ],
                [
                    'attributeId' => ['value' => 'MASTKARTB'],
                    'attributeValue' => ['value' => (int) $article->master_box_width],
                ],
                [
                    'attributeId' => ['value' => 'MASTKARTH'],
                    'attributeValue' => ['value' => (int) $article->master_box_height],
                ],
                [
                    'attributeId' => ['value' => 'MASTKARTD'],
                    'attributeValue' => ['value' => (int) $article->master_box_depth],
                ],
                [
                    'attributeId' => ['value' => 'STRLINKB'],
                    'attributeValue' => ['value' => (int) $article->inner_box_width],
                ],
                [
                    'attributeId' => ['value' => 'STRLINKH'],
                    'attributeValue' => ['value' => (int) $article->inner_box_height],
                ],
                [
                    'attributeId' => ['value' => 'STRLINKD'],
                    'attributeValue' => ['value' => (int) $article->inner_box_depth],
                ],
                [
                    'attributeId' => ['value' => 'VIKTIK'],
                    'attributeValue' => ['value' => (int) $article->inner_box_weight],
                ],
                [
                    'attributeId' => ['value' => 'VARUMÄRKE'],
                    'attributeValue' => ['value' => (int) $article->brand],
                ],
                [
                    'attributeId' => ['value' => 'WEBBSHOP'],
                    'attributeValue' => ['value' => (int) $article->is_webshop],
                ],
            ]
        ];

        return $this->callAPI('PUT', '/v1/inventory/' . $article->article_number, $data);
    }

}
