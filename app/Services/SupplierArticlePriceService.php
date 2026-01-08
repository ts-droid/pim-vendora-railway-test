<?php

namespace App\Services;

use App\Http\Controllers\CurrencyConvertController;
use App\Models\Supplier;
use App\Models\SupplierArticlePrice;

class SupplierArticlePriceService
{
    public function getUnitCostForSupplier(string $articleNumber, Supplier $supplier): float
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

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
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $supplierArticlePrice = SupplierArticlePrice::where('article_number', $articleNumber)->first();

        if (!$supplierArticlePrice) {
            return null;
        }

        return $supplierArticlePrice;
    }

    public function createSupplierArticlePrice(array $attributes): SupplierArticlePrice
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        SupplierArticlePrice::where('article_number', $attributes['article_number'])->delete();

        return SupplierArticlePrice::create($attributes);
    }

    public function updateSupplierArticlePrice(SupplierArticlePrice $supplierArticlePrice, array $attributes): SupplierArticlePrice
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $supplierArticlePrice->update($attributes);
        return $supplierArticlePrice;
    }
}
