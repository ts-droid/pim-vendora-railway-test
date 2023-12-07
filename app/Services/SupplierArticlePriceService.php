<?php

namespace App\Services;

use App\Models\SupplierArticlePrice;

class SupplierArticlePriceService
{
    public function getSupplierArticlePrice(string $articleNumber): ?SupplierArticlePrice
    {
        $supplierArticlePrice = SupplierArticlePrice::where('article_number', $articleNumber)->first();

        if (!$supplierArticlePrice) {
            return null;
        }

        return $supplierArticlePrice;
    }

    public function createSupplierArticlePrice(array $attributes): SupplierArticlePrice
    {
        SupplierArticlePrice::where('article_number', $attributes['article_number'])->delete();

        return SupplierArticlePrice::create($attributes);
    }

    public function updateSupplierArticlePrice(SupplierArticlePrice $supplierArticlePrice, array $attributes): SupplierArticlePrice
    {
        $supplierArticlePrice->update($attributes);
        return $supplierArticlePrice;
    }
}
