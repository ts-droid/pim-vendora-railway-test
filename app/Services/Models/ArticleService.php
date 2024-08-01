<?php

namespace App\Services\Models;

use App\Enums\ArticleStatus;
use App\Http\Controllers\ArticleCategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\WgrController;
use App\Models\Article;
use App\Services\VismaNet\VismaNetArticleService;

class ArticleService
{
    public function handleCreation(Article $article): void
    {
        // TODO: Implement logic for handling article creation
    }

    public function handleUpdate(Article $article, array $changes): void
    {
        if ($article->article_number !== '1001-1') {
            return;
        }

        if (!$changes) {
            return;
        }

        // Push update to WGR
        $this->pushToWGR($article, $changes);

        // Push update to Visma.net
        $vismaNetArticleService = new VismaNetArticleService();
        $vismaNetArticleService->updateArticle($article);
    }

    private function pushToWGR(Article $article, array $changes): void
    {
        $data = [
            'isHidden' => (bool) $article->is_webshop,
            'isBackOrder' => (bool) $article->is_backorder,
            'reviewLinksJSON' => (string) $article->review_links ?: '[]',
            'width' => (int) $article->width,
            'height' => (int) $article->height,
            'depth' => (int) $article->depth,
            'widthInner' => (int) $article->inner_box_width,
            'heightInner' => (int) $article->inner_box_height,
            'depthInner' => (int) $article->inner_box_depth,
            'widthMaster' => (int) $article->master_box_width,
            'heightMaster' => (int) $article->master_box_height,
            'depthMaster' => (int) $article->master_box_depth,
            'weight' => (int) $article->weight,
            'weightInner' => (int) $article->inner_box_weight,
            'weightMaster' => (int) $article->master_box_weight,
            'innerbox' => (int) $article->inner_box,
            'masterbox' => (int) $article->master_box,
            'EANCode' => (string) $article->ean,
            'wrightArticleNumber' => (string) $article->wright_article_number,
            'customsCode' => (string) $article->hs_code,
            'eol' => $article->status !== ArticleStatus::Active->value,
            'categoryId' => [],
            'googleProductCategory' => (string) $article->google_product_category,
        ];

        // Add categories
        $articleCategoryIDs = json_decode($article->category_ids, true) ?: [];

        if ($articleCategoryIDs) {
            $articleCategoryController = new ArticleCategoryController();
            $categoryPaths = $articleCategoryController->getCategoryPaths();

            $wgrController = new WgrController();
            $wgrCategories = $wgrController->getCategories();

            foreach ($categoryPaths as $categoryPath) {
                if (!in_array($categoryPath['id'], $articleCategoryIDs)) {
                    continue;
                }

                foreach ($wgrCategories as $wgrCategory) {
                    if ($wgrCategory['path'] === $categoryPath['path']) {
                        $data['categoryId'][] = $wgrCategory['id'];
                        break;
                    }
                }
            }
        }

        // Add videos
        $videos = json_decode($article->video, true);
        $data['embedVideo'] = $videos[0] ?? '';
        $data['embedVideo2'] = $videos[1] ?? '';
        $data['embedVideo3'] = $videos[2] ?? '';

        // Add language fields
        $languages = (new LanguageController())->getAllLanguages();
        foreach ($languages as $language) {
            $data['title_' . $language->language_code] = (string) $article->{'shop_title_' . $language->language_code};
            $data['description_' . $language->language_code] = (string) $article->{'shop_description_' . $language->language_code};
        }

        // Add currency fields
        foreach (CurrencyController::SUPPORTED_CURRENCIES as $currency) {
            $data['price_' . $currency] = (float) $article->{'rek_price_' . $currency};
        }

        $wgrController = new WgrController();
        $wgrController->updateArticle($article->article_number, $data);


        // TODO: Handle images

        // TODO: Handle files
    }
}
