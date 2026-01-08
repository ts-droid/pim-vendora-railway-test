<?php

namespace App\Http\Controllers;

use App\Services\SupplierArticlePriceService;
use Illuminate\Http\Request;

class SupplierPriceController extends Controller
{
    public function store(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
                    'price' => (float) str_replace(',', '.', $price),
                    'currency' => (string) $currency,
                ]);
            }

        }

        return ApiResponseController::success();
    }
}
