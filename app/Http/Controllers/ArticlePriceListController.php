<?php

namespace App\Http\Controllers;

use App\Services\ArticlePriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticlePriceListController extends Controller
{
    public function customer(Request $request)
    {
        $customerID = (int) $request->input('customer_id');
        $currency = (string) $request->input('currency');
        $brandName = (string) $request->input('brand_name');
        $sorting = (string) $request->input('sorting');
        $articleNumber = (string) $request->input('article_number');

        if (!$customerID || !$currency) {
            return ApiResponseController::error('Missing "customer_id" or "currency" parameter.');
        }

        $supplierNumber = '';

        if ($brandName) {
            $supplierNumber = (string) DB::table('suppliers')
                ->select('number')
                ->where('brand_name', '=', $brandName)
                ->pluck('number')
                ->first();
        }

        $priceService = new ArticlePriceService();
        $priceList  = $priceService->getPriceList($customerID, $currency, $supplierNumber, $sorting, $articleNumber);

        return ApiResponseController::success($priceList);
    }
}
