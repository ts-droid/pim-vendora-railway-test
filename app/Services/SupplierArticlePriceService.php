<?php

namespace App\Services;

use App\Http\Controllers\CurrencyConvertController;
use App\Models\Supplier;
use App\Models\SupplierArticlePrice;

class SupplierArticlePriceService
{
    public function getUnitCostForSupplier(string $articleNumber, Supplier $supplier): float
    {
        $supplierPrice = $this->getSupplierArticlePrice($articleNumber);

        if (!$supplierPrice) {
            return 0;
        }

        $unitCost = $supplierPrice->price;

        // Convert to supplier currency
        if ($supplier->currency != $supplierPrice->currency) {
            $currencyConverter = new CurrencyConvertController();
            $unitCost = $currencyConverter->convert($unitCost, $supplierPrice->currency, $supplier->currency);
        }

        return $unitCost;
    }

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
