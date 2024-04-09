<?php

namespace App\Http\Controllers;

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
}
