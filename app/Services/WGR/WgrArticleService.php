<?php

namespace App\Services\WGR;

use App\Enums\ArticleStatus;
use App\Http\Controllers\ArticleCategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CurrencyConvertController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\WgrController;
use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\ArticleImage;
use App\Services\SupplierArticlePriceService;
use App\Utilities\ImageComparisonUtility;

class WgrArticleService
{
    public function createArticle(Article $article): void
    {
        if (!in_array($article->status, ['Active', 'NoPurchases'])) {
            return;
        }

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
                $remoteImageID = $this->uploadImage($image, $productID);

                $image->update(['wgr_id' => $remoteImageID]);
            }
        }

        // Create files
        $files = ArticleFile::where('article_id', $article->id)->get();
        if ($files) {
            foreach ($files as $file) {
                $remoteFileID = $this->uploadFile($file, $productID);

                $file->update(['wgr_id' => $remoteFileID]);
            }
        }
    }

    public function updateArticle(Article $article): void
    {
        if (!in_array($article->status, ['Active', 'NoPurchases'])) {
            return;
        }

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


        // Handle file updates
        $files = ArticleFile::where('article_id', $article->id)->get();
        $remoteFiles = $wgrController->getArticleFiles($productID);

        $checkedFileIDs = [];

        // Remove deleted files
        if ($remoteFiles) {
            foreach ($remoteFiles as $remoteFile) {
                foreach ($files as $file) {
                    if ($file->id == $remoteFile['id']) {
                        $checkedFileIDs[] = $file->id;
                        continue 2;
                    }
                }

                // Remove file from WGR
                $wgrController->makeRequest('ProductFile.delete', [
                    'id' => $remoteFile['id']
                ]);
            }
        }

        // Upload new files
        foreach ($files as $file) {
            if (in_array($file->id, $checkedFileIDs)) {
                continue;
            }

            $remoteFileID = $this->uploadFile($file, $productID);

            $file->update(['wgr_id' => $remoteFileID]);
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

                        $image->update(['wgr_id' => $remoteImage['imageId']]);

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

            $remoteImageID = $this->uploadImage($image, $productID);

            $image->update(['wgr_id' => $remoteImageID]);
        }

        // Set list order for images
        $listOrders = ArticleImage::where('article_id', $article->id)
            ->orderBy('list_order', 'ASC')
            ->pluck('wgr_id')
            ->toArray();

        $wgrController->makeRequest('productImage.listorder', [
            'imageIds' => implode(',', $listOrders)
        ]);
    }

    private function uploadFile(ArticleFile $file, int $productID): int
    {
        $fileData = [
            'productID' => $productID,
            'url' => $file->path_url,
            'filename' => $file->filename
        ];

        foreach (LanguageController::SUPPORTED_EXTERNAL_LANGUAGES['wgr'] as $languageCode) {
            $fileData['title_' . $languageCode] = basename($file->filename);
        }

        $wgrController = new WgrController();
        $response = $wgrController->makeRequest('ProductFile.createurl', $fileData);

        return (int) ($imagesResponse[0]['result'] ?? 0);
    }

    private function uploadImage(ArticleImage $image, int $productID): int
    {
        $wgrController = new WgrController();
        $response = $wgrController->createArticleImageFromURL(
            $productID,
            $image->filename,
            $image->path_url
        );

        return (int) ($imagesResponse[0]['result'] ?? 0);
    }

    public function getPostData(Article $article): array
    {
        $postData = [
            'isBackOrder' => (bool) $article->is_backorder,
            'reviewLinksJSON' => (string) $article->review_links ?: '[]',
            'brand' => (string) $article->brand,
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
            'defaultBox' => 0, // 0 = Default, 1 = Master, 2 = Inner
        ];

        if ($article->publish_at) {
            $postData['timeCreated'] = date('Y-m-d H:i:s', strtotime($article->publish_at));
        }

        $isHidden = false;

        if (!$article->is_webshop) {
            $isHidden = true;
        }

        if ($article->is_dropship) {
            if ($article->minimum_order_quantity == 'master') {
                $postData['defaultBox'] = 1;
            }
            elseif ($article->minimum_order_quantity == 'inner') {
                $postData['defaultBox'] = 2;
            }
        }

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
            $postData['title_' . $language->language_code] = trim((string) $article->{'shop_title_' . $language->language_code});
            $postData['description_' . $language->language_code] = trim((string) $article->{'shop_description_' . $language->language_code});

            if (!$postData['title_' . $language->language_code]
                || !$postData['description_' . $language->language_code]) {
                $isHidden = true;
            }
        }

        // Add currency fields
        foreach (CurrencyController::SUPPORTED_CURRENCIES as $currency) {
            $postData['price_' . $currency] = (float) $article->{'rek_price_' . $currency};
            $postData['lifestylestorePrice_' . $currency] = (float) $article->{'rek_price_' . $currency};

            // Calculate reseller price
            if ($article->standard_reseller_margin) {
                $retailPrice = $article->{'rek_price_' . $currency} * 0.8;
                $retailPrice = $retailPrice * (1 - ($article->standard_reseller_margin / 100));
                $retailPrice = round($retailPrice, 2);

                // Must store including VAT to be correct in WGR
                $retailPrice = $retailPrice / 0.8;
                $postData['retailPrice_' . $currency] = $retailPrice;
            }

            if (!$postData['price_' . $currency]) {
                $isHidden = true;
            }
        }

        $postData['isHidden'] = $isHidden;

        return $postData;
    }
}
