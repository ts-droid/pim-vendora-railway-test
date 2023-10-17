<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;

class ArticleCategoryController extends Controller
{
    public function getCategoryTree(array $categoryIDs, int $parentID = 0)
    {
        $categories = ArticleCategory::whereIn('id', $categoryIDs)
            ->where('parent_id', $parentID)
            ->get();

        if (!$categories) {
            return [];
        }

        $categories = $categories->toArray();

        foreach ($categories as &$category) {
            $category['children'] = $this->getCategoryTree($categoryIDs, $category['id']);
        }

        return $categories;
    }

    public function getCategoryByTitle(string $title, int $parentID = 0)
    {
        $articleCategory = ArticleCategory::where('title_en', $title)
            ->where('parent_id', $parentID)
            ->first();

        return $articleCategory ?: null;
    }

    public function createCategory(string $title, int $parentID)
    {
        return ArticleCategory::create([
            'title_en' => $title,
            'parent_id' => $parentID,
        ]);
    }
}
