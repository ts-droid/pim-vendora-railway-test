<?php

namespace App\Http\Controllers;

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
}
