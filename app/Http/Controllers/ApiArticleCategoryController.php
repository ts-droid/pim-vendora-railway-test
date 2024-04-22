<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;

class ApiArticleCategoryController extends Controller
{
    public function getAll()
    {
        $categoryController = new ArticleCategoryController();

        $categories = $categoryController->getCategoryTree(
            $categoryController->getAllCategoryIDs()
        );

        return ApiResponseController::success($categories);
    }

    public function get(ArticleCategory $articleCategory)
    {
        $response = $articleCategory->toArray();

        $response['articles'] = Article::whereJsonContains('category_ids', $articleCategory->id)
            ->get()
            ->toArray();

        return ApiResponseController::success($response);
    }

    public function store(Request $request)
    {
        $categoryController = new ArticleCategoryController();

        $articleCategory = $categoryController->createCategory(
            $request->all(),
            (int) $request->input('parent_id')
        );

        return ApiResponseController::success($articleCategory->toArray());
    }

    public function update(Request $request, ArticleCategory $articleCategory)
    {
        $categoryController = new ArticleCategoryController();

        $articleCategory = $categoryController->updateCategory($articleCategory, $request->all());

        return ApiResponseController::success($articleCategory->toArray());
    }
}
