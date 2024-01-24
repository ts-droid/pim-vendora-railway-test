<?php

namespace App\Http\Controllers;

use App\Services\ArticlePriceService;
use Illuminate\Http\Request;

class ArticlePriceListController extends Controller
{
    public function customer(Request $request)
    {
        $customerID = (int) $request->input('customer_id');
        $currency = (string) $request->input('currency');

        if (!$customerID || !$currency) {
            return ApiResponseController::error('Missing "customer_id" or "currency" parameter.');
        }

        $priceService = new ArticlePriceService();

        $priceList  = $priceService->getPriceList($customerID, $currency);

        return ApiResponseController::success($priceList);
    }
}
