<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateArticleImageData;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductSeoController extends Controller
{
    public function queueImageData(Request $request)
    {
        $imageIDs = $request->input('image_ids', '');
        $imageIDs = explode(',', $imageIDs);
        $imageIDs = array_map('intval', $imageIDs);
        $imageIDs = array_filter($imageIDs);

        foreach ($imageIDs as $imageID) {
            GenerateArticleImageData::dispatch($imageID)->onQueue('low');
        }

        return ApiResponseController::success();
    }

    public function queueBrandImageData(Request $request)
    {
        $supplierID = $request->input('supplier_id', 0);
        if (!$supplierID) {
            return ApiResponseController::error('Parameter "supplier_id" is required');
        }

        $supplierNumber = Supplier::where('id', '=', $supplierID)->value('number');
        if (!$supplierNumber) {
            return ApiResponseController::error('Supplier not found');
        }

        // Fetch all uncompleted image IDs within the supplier
        $languageController = new LanguageController();
        $languages = $languageController->getAllLanguages();

        $imageIDs = DB::table('article_images')
            ->join('articles', 'articles.id', '=', 'article_images.article_id')
            ->join('suppliers', 'suppliers.number', '=', 'articles.supplier_number')
            ->select('article_images.id AS id')
            ->where('suppliers.number', '=', $supplierNumber)
            ->where(function($query) use ($languages) {
                foreach($languages as $language) {
                    $query->orWhere('alt_text_' . $language->language_code, '=', '')
                        ->orWhereNull('alt_text_' . $language->language_code);
                }
            })
            ->get()
            ->pluck('id')
            ->toArray();

        foreach ($imageIDs as $imageID) {
            GenerateArticleImageData::dispatch($imageID)->onQueue('low');
        }

        return ApiResponseController::success();
    }
}
