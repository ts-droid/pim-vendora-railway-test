<?php

namespace App\Http\Controllers;

use App\Models\ArticleReview;
use App\Services\ArticleReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ArticleReviewController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'article_number' => 'required',
            'content' => 'required',
            'stars' => 'required',
            'default_language' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $articleReviewService = new ArticleReviewService();
        $articleReview = $articleReviewService->store([
            'article_number' => $request->input('article_number'),
            'name' => $request->input('name', ''),
            'content' => $request->input('content', ''),
            'ip' => $request->input('ip', ''),
            'stars' => $request->input('stars'),
            'default_language' => $request->input('default_language'),
            'published_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$articleReview) {
            return ApiResponseController::error('Failed to create review');
        }

        return ApiResponseController::success($articleReview->toArray());
    }

    public function update(Request $request, ArticleReview $articleReview)
    {
        $articleReviewService = new ArticleReviewService();
        $articleReviewService->update($articleReview, $request->all());

        return ApiResponseController::success();
    }

    public function delete(Request $request, ArticleReview $articleReview)
    {
        $articleReviewService = new ArticleReviewService();
        $articleReviewService->delete($articleReview);

        return ApiResponseController::success();
    }
}
