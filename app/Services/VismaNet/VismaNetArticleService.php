<?php

namespace App\Services\VismaNet;

use App\Models\Article;

class VismaNetArticleService extends VismaNetApiService
{
    private array $crossReferences = [];

    public function createArticle(Article $article): void
    {
        $this->callAPI('POST', '/v1/inventory', $this->getPostData($article, true));

        // Set cross references
        if ($article->ean) {
            $this->setCrossReferences($article->article_number, 'Barcode', $article->ean);
        }
        if ($article->wright_article_number) {
            $this->setCrossReferences($article->article_number, 'VPN', $article->wright_article_number);
        }
    }

    public function updateArticle(Article $article): void
    {
        $this->callAPI('PUT', '/v1/inventory/' . $article->article_number, $this->getPostData($article));

        // Update cross references
        if ($article->ean) {
            $this->setCrossReferences($article->article_number, 'Barcode', $article->ean);
        }
        if ($article->wright_article_number) {
            $this->setCrossReferences($article->article_number, 'VPN', $article->wright_article_number);
        }
    }

    private function setCrossReferences(string $articleNumber, string $alternateType, mixed $value): void
    {
        // Load existing cross references
        if (!isset($this->crossReferences[$articleNumber])) {
            $response = $this->callAPI('GET', '/v1/inventory/' . $articleNumber . '/crossReferences');
            $this->crossReferences[$articleNumber] = $response['response'] ?? '';
        }

        // Try to update existing value
        foreach ($this->crossReferences[$articleNumber] as $crossReference) {
            if ($crossReference['alternateType'] == $alternateType) {
                $this->callAPI('PUT', '/v1/inventory/' . $articleNumber . '/crossReferences/' . $alternateType . '/' . $crossReference['alternateID'], [
                    'alternateID' => ['value' => $value],
                ]);
                return;
            }
        }

        // Create cross reference if not updated
        $this->callAPI('POST', '/v1/inventory/' . $articleNumber . '/crossReferences', [
            'alternateType' => ['value' => $alternateType],
            'alternateID' => ['value' => $value],
        ]);
    }

    private function getPostData(Article $article, bool $isNewArticle = false): array
    {
        $data = [
            'status' => ['value' => $article->status],
            'type' => ['value' => $article->article_type],
            'itemClass' => ['value' => $article->article_type],
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
                    'attributeId' => ['value' => 'VIKTMAST'],
                    'attributeValue' => ['value' => (int) $article->master_box_weight],
                ],
                [
                    'attributeId' => ['value' => 'VARUMÄRKE'],
                    'attributeValue' => ['value' => $article->brand],
                ],
                [
                    'attributeId' => ['value' => 'WEBBSHOP'],
                    'attributeValue' => ['value' => (int) $article->is_webshop],
                ],
            ]
        ];

        if ($isNewArticle) {
            $data['inventoryNumber'] = ['value' => $article->article_number];
        }

        // Set supplier
        if ($article->supplier_number) {

            $currentSupplierNumber = '';
            if (!$isNewArticle) {
                $response = $this->callAPI('GET', '/v1/inventory/' . $article->article_number);
                $remoteArticle = $response['response'] ?? [];
                $currentSupplierNumber = $remoteArticle['supplierDetails'][0]['supplierId'] ?? '';
            }

            if ($currentSupplierNumber != $article->supplier_number) {
                $data['supplierDetails'] = [
                    [
                        'operation' => ($isNewArticle ? 'Insert' : 'Update'),
                        'active' => ['value' => true],
                        'default' => ['value' => true],
                        'supplierID' => ['value' => $article->supplier_number],
                        'purchaseUnit' => ['value' => 'STYCK'],
                    ]
                ];
            }
        }

        return $data;
    }
}
