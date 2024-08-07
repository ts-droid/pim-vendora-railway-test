<?php

namespace App\Services\WGR;

use App\Enums\ArticleStatus;
use App\Http\Controllers\ArticleCategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\WgrController;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Utilities\ImageComparisonUtility;

class WgrArticleService
{
    public function createArticle(Article $article): void
    {
        $postData = $this->getPostData($article);
        $postData['articleNumber'] = $article->article_number;

        if (count($postData['categoryId']) === 0) {
            return;
        }

        $wgrController = new WgrController();
        $response = $wgrController->createArticle($postData);

        $productID = (int) ($response[0]['result']['productId'] ?? 0);
        if (!$productID) {
            return;
        }

        // Create images
        $images = ArticleImage::where('article_id', $article->id)->get();
        if ($images) {
            foreach ($images as $image) {
                $this->uploadImage($image, $productID);
            }
        }
    }

    public function updateArticle(Article $article): void
    {
        $wgrController = new WgrController();

        // Make sure the article exists in WGR
        $wgrArticle = $wgrController->getArticle($article->article_number);
        if (!($wgrArticle['id'] ?? null)) {
            $this->createArticle($article);
            return;
        }

        // Update the article
        $response = $wgrController->updateArticle($article->article_number, $this->getPostData($article));

        $productID = (int) ($response[0]['result']['productId'] ?? 0);
        if (!$productID) {
            return;
        }

        // Handle image updates
        $images = ArticleImage::where('article_id', $article->id)->get();
        $remoteImages = $wgrController->getArticleImages($article->article_number);

        $checkedImageIDs = [];

        // Remove deleted images
        if ($remoteImages) {
            foreach ($remoteImages as $remoteImage) {
                foreach ($images as $image) {
                    $isSimilar = ImageComparisonUtility::isBase64ImageSimilar($image->getBase64(), $remoteImage['base64']);
                    if ($isSimilar) {
                        $checkedImageIDs[] = $image->id;
                        continue 2;
                    }
                }

                // The image was not found in the local images, so it must have been deleted
                $wgrController->deleteArticleImage($remoteImage['imageId']);
            }
        }

        // Upload new images
        foreach ($images as $image) {
            if (in_array($image->id, $checkedImageIDs)) {
                continue;
            }

            $this->uploadImage($image, $productID);
        }
    }

    private function uploadImage(ArticleImage $image, int $productID): void
    {
        $wgrController = new WgrController();
        $wgrController->createArticleImage(
            $productID,
            $image->filename,
            $image->getBase64()
        );
    }

    private function getPostData(Article $article): array
    {
        $postData = [
            'isHidden' => !$article->is_webshop,
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
        $articleCategoryIDs = $article->category_ids ?: [];

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
                        $postData['categoryId'][] = $wgrCategory['id'];
                        break;
                    }
                }
            }
        }

        // Add videos
        $videos = json_decode($article->video, true);
        $postData['embedVideo'] = $videos[0] ?? '';
        $postData['embedVideo2'] = $videos[1] ?? '';
        $postData['embedVideo3'] = $videos[2] ?? '';

        // Add language fields
        $languages = (new LanguageController())->getAllLanguages();
        foreach ($languages as $language) {
            $postData['title_' . $language->language_code] = (string) $article->{'shop_title_' . $language->language_code};
            $postData['description_' . $language->language_code] = (string) $article->{'shop_description_' . $language->language_code};
        }

        // Add currency fields
        foreach (CurrencyController::SUPPORTED_CURRENCIES as $currency) {
            $postData['price_' . $currency] = (float) $article->{'rek_price_' . $currency};
        }

        return $postData;
    }
}
