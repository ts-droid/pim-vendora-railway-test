<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;

class ArticleCategoryController extends Controller
{
    public function getAllCategoryIDs()
    {
        return ArticleCategory::pluck('id')->toArray();
    }

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

    public function createCategory(array $data, int $parentID)
    {
        $createData = [
            'parent_id' => $parentID,
        ];

        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $locale) {
            $value = $data['title_' . $locale->language_code] ?? null;

            if ($value) {
                $createData['title_' . $locale->language_code] = $value;
            }
        }

        return ArticleCategory::create($createData);
    }

    public function updateCategory(ArticleCategory $category, array $data)
    {
        $updateData = [];

        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $locale) {
            $value = $data['title_' . $locale->language_code] ?? null;

            if ($value) {
                $updateData['title_' . $locale->language_code] = $value;
            }
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int) $data['is_active'];
        }

        $category->update($updateData);

        return $category;
    }
}
