<?php

namespace App\Services\VismaNet;

use App\Models\Article;

class VismaNetArticleService extends VismaNetApiService
{
    private array $crossReferences = [];

    private array $attributes = [];

    public function createArticle(Article $article): void
    {
        if ($article->brand) {
            $this->createBrand($article->brand);
        }

        $postData = $this->getPostData($article, true);

        $response = $this->callAPI('POST', '/v1/inventory', $postData);
        if (!$response['success']) {
            throw new \Exception('Failed to create article in Visma.net: ' . ($response['response']['response'] ?? 'Unknown error'));
        }

        // Set cross references
        if ($article->ean) {
            $this->setCrossReferences($article->article_number, 'Barcode', $article->ean, 'STYCK');
        }
        if ($article->ean_inner_box) {
            $this->setCrossReferences($article->article_number, 'Barcode', $article->ean_inner_box, 'INNE10');
        }
        if ($article->ean_master_box) {
            $this->setCrossReferences($article->article_number, 'Barcode', $article->ean_master_box, 'MAS100');
        }
        if ($article->wright_article_number) {
            $this->setCrossReferences($article->article_number, 'VPN', $article->wright_article_number);
        }
    }

    public function updateArticle(Article $article): void
    {
        // Check if article exists in Visma.net
        $checkResponse = $this->callAPI('GET', '/v1/inventory/' . $article->article_number);
        if (!$checkResponse['success']) {
            $this->createArticle($article);
            return;
        }

        $this->callAPI('PUT', '/v1/inventory/' . $article->article_number, $this->getPostData($article));

        // Update cross references
        if ($article->ean) {
            $this->setCrossReferences($article->article_number, 'Barcode', $article->ean, 'STYCK');
        }
        if ($article->ean_inner_box) {
            $this->setCrossReferences($article->article_number, 'Barcode', $article->ean_inner_box, 'INNE10');
        }
        if ($article->ean_master_box) {
            $this->setCrossReferences($article->article_number, 'Barcode', $article->ean_master_box, 'MAS100');
        }
        if ($article->wright_article_number) {
            $this->setCrossReferences($article->article_number, 'VPN', $article->wright_article_number);
        }
    }

    private function setCrossReferences(string $articleNumber, string $alternateType, mixed $value, string $unit = ''): void
    {
        // Load existing cross references
        if (!isset($this->crossReferences[$articleNumber])) {
            $response = $this->callAPI('GET', '/v1/inventory/' . $articleNumber . '/crossReferences');
            $this->crossReferences[$articleNumber] = $response['response'] ?? [];
        }

        // Try to update existing value
        foreach ($this->crossReferences[$articleNumber] as $crossReference) {
            $crossReferenceType = $crossReference['alternateType'] ?? '';
            $crossReferenceUnit = $crossReference['uom'] ?? '';

            if ($crossReferenceType == $alternateType && (!$unit || $crossReferenceUnit == $unit)) {
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

    public function getPostData(Article $article, bool $isNewArticle = false): array
    {
        $description = preg_replace('/[^a-zA-Z0-9\s\-åäöÅÄÖ()]/u', '', $article->description);

        $data = [
            'status' => ['value' => $article->status],
            'type' => ['value' => $article->article_type],
            'description' => ['value' => $description],
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
                    'attributeValue' => ['value' => $this->getAttributeValueID('VARUMÄRKE', $article->brand)]
                ],
                [
                    'attributeId' => ['value' => 'WEBBSHOP'],
                    'attributeValue' => ['value' => (int) $article->is_webshop],
                ],
            ]
        ];

        if ($isNewArticle) {
            $data['inventoryNumber'] = ['value' => $article->article_number];
            $data['itemClass'] = ['value' => $article->article_type];
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

    public function getAttributeValueID(string $attributeID, string $valueDescription): string
    {
        $attributes = $this->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute['attributeID'] != $attributeID) {
                continue;
            }

            foreach ($attribute['details'] as $detail) {
                if ($detail['description'] != $valueDescription) {
                    continue;
                }

                return $detail['valueId'];
            }
        }

        return '';
    }

    public function createBrand(string $brand): void
    {
        $attributes = $this->getAttributes();

        $attributeID = preg_replace('/\s+/', '', $brand);;
        $attributeDescription = $brand;

        foreach ($attributes as $attribute) {
            if ($attribute['attributeID'] == 'VARUMÄRKE') {

                foreach($attribute['details'] as $detail) {
                    if ($detail['valueId'] == $attributeID) {
                        return;
                    }
                }

                $this->callAPI('PUT', '/v1/attribute/' . $attribute['attributeID'], [
                    'details' => [
                        [
                            'operation' => 'Insert',
                            'valueId' => ['value' => $attributeID],
                            'description' => ['value' => $attributeDescription],
                        ]
                    ]
                ]);

                $this->attributes = [];

                return;
            }
        }
    }

    private function getAttributes()
    {
        if (count($this->attributes)) {
            return $this->attributes;
        }

        $attributes = $this->callAPI('GET', '/v1/attribute');
        $this->attributes = $attributes['response'] ?? [];

        return $this->attributes;
    }
}
