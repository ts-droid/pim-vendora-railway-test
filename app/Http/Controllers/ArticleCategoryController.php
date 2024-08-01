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

    public function getCategoryPaths()
    {
        $categories = \App\Models\ArticleCategory::all()->toArray();

        // Build a map of categories
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category['id']] = $category;
        }

        function buildCategoryPath(&$category, $categoryMap) {
            if ($category['parent_id'] == 0) {
                // If it's a root category, the path is just its title
                return $category['title_en'];
            } else {
                // Recursively build the path
                $parentCategory = $categoryMap[$category['parent_id']];
                $parentPath = buildCategoryPath($parentCategory, $categoryMap);
                return $parentPath . ' - ' . $category['title_en'];
            }
        }

        // Add the "path" value to each category
        $categoryPaths = [];
        foreach ($categories as $category) {
            $categoryPaths[] = [
                'id' => $category['id'],
                'path' => buildCategoryPath($category, $categoryMap),
            ];
        }

        return $categoryPaths;
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
            $title = $data['title_' . $locale->language_code] ?? null;
            if ($title) {
                $updateData['title_' . $locale->language_code] = $title;
            }

            $metaDescription = $data['meta_description_' . $locale->language_code] ?? null;
            if ($metaDescription) {
                $updateData['meta_description_' . $locale->language_code] = $metaDescription;
            }
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int) $data['is_active'];
        }

        $category->update($updateData);

        return $category;
    }
}
