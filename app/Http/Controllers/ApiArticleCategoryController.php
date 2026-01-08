<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;

class ApiArticleCategoryController extends Controller
{
    public function getAll()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $categoryController = new ArticleCategoryController();

        $categories = $categoryController->getCategoryTree(
            $categoryController->getAllCategoryIDs()
        );

        return ApiResponseController::success($categories);
    }

    public function get(ArticleCategory $articleCategory)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $response = $articleCategory->toArray();

        $response['articles'] = Article::whereJsonContains('category_ids', $articleCategory->id)
            ->get()
            ->toArray();

        return ApiResponseController::success($response);
    }

    public function store(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $categoryController = new ArticleCategoryController();

        $articleCategory = $categoryController->createCategory(
            $request->all(),
            (int) $request->input('parent_id')
        );

        return ApiResponseController::success($articleCategory->toArray());
    }

    public function update(Request $request, ArticleCategory $articleCategory)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $categoryController = new ArticleCategoryController();

        $articleCategory = $categoryController->updateCategory($articleCategory, $request->all());

        return ApiResponseController::success($articleCategory->toArray());
    }

    public function connect(Request $request, ArticleCategory $articleCategory)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $article = Article::find($request->input('article_id'));
        if (!$article) {
            return ApiResponseController::error('Article not found');
        }

        $categoryIDs = array_merge($article->category_ids, [$articleCategory->id]);
        $categoryIDs = array_unique($categoryIDs);

        $article->update([
            'category_ids' => $categoryIDs
        ]);

        return ApiResponseController::success();
    }

    public function disconnect(Request $request, ArticleCategory $articleCategory)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $article = Article::find($request->input('article_id'));
        if (!$article) {
            return ApiResponseController::error('Article not found');
        }

        $categoryIDs = array_diff($article->category_ids, [$articleCategory->id]);

        $article->update([
            'category_ids' => $categoryIDs
        ]);

        return ApiResponseController::success();
    }
}
