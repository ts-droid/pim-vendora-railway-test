<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateArticleImageData;
use App\Jobs\GenerateArticleMetaData;
use Illuminate\Http\Request;

class ProductSeoController extends Controller
{
    public function queueMetaData(Request $request)
    {
        $articleIDs = $request->input('article_ids', '');
        $articleIDs = explode(',', $articleIDs);
        $articleIDs = array_map('intval', $articleIDs);
        $articleIDs = array_filter($articleIDs);

        foreach ($articleIDs as $articleID) {
            GenerateArticleMetaData::dispatch($articleID)->onQueue('low');
        }

        return ApiResponseController::success();
    }

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
}
