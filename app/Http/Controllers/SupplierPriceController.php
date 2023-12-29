<?php

namespace App\Http\Controllers;

use App\Services\SupplierArticlePriceService;
use Illuminate\Http\Request;

class SupplierPriceController extends Controller
{
    public function store(Request $request)
    {
        $supplierPriceService = new SupplierArticlePriceService();

        $prices = $request->get('prices');

        if ($prices && is_array($prices)) {

            foreach ($prices as $priceRow) {
                $articleNumber = $priceRow['article_number'] ?? null;
                $price = $priceRow['price'] ?? null;
                $currency = $priceRow['currency'] ?? null;

                if (!$articleNumber || !$price || !$currency) {
                    continue;
                }

                $supplierPriceService->createSupplierArticlePrice([
                    'article_number' => (string) $articleNumber,
                    'price' => (float) $price,
                    'currency' => (string) $currency,
                ]);
            }

        }

        return ApiResponseController::success();
    }
}
