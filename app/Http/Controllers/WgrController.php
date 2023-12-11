<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class WgrController extends Controller
{
    private string $apiDomain;

    private string $apiUsername;

    private string $apiPassword;

    function __construct()
    {
        $this->apiDomain = env('WGR_API_DOMAIN', '');
        $this->apiUsername = env('WGR_API_USERNAME', '');
        $this->apiPassword = env('WGR_API_PASSWORD', '');
    }

    /**
     * Fetches all data from the WGR API
     *
     * @param boolean $forceAll
     * @param boolean $skipImages
     * @return void
     */
    public function fetchAll(bool $forceAll = false, bool $skipImages = false): void
    {
        $this->fetchProductData(
            ($forceAll ? '' : null),
            $skipImages
        );

        StatusIndicatorController::ping('WGR sync', 86400);
    }

    /**
     * Fetches product data from the WGR API
     *
     * @param mixed $updatedAfter
     * @param boolean $skipImages
     * @return void
     */
    public function fetchProductData(mixed $updatedAfter = null, bool $skipImages = false): void
    {
        $fetchTime = date('Y-m-d H:i:s');

        $params = [
            'getImages' => true,
        ];

        if (is_null($updatedAfter)) {
            $updatedAfter = ConfigController::getConfig('wgr_last_article_fetch');
        }

        if ($updatedAfter) {
            $params['updatedFrom'] = $updatedAfter;
        }

        $products = $this->makeRequest('Article.get', $params);
        $products = $products[0]['result'] ?? [];

        $articleController = new ArticleController();

        $languages = (new LanguageController())->getAllLanguages();

        foreach ($products as $productData) {
            $articleNumber = $productData['articleNumber'] ?? '';

            $article = Article::where('article_number', $articleNumber)->first();

            if (!$article) {
                continue;
            }

            echo 'Found article: ' . $articleNumber . PHP_EOL;

            // Fetch article data from API response
            $articleData = [
                'video' => ($productData['embedVideo'] ?? ''),
                'webshop_created_at' => $productData['timeCreated'] ?? '',
                'review_links' => json_encode(json_decode($productData['reviewLinksJSON'], true)),
                'is_hidden' => ($productData['isHidden'] ?? false) ? 1 : 0,
                'images' => []
            ];

            // Currency fields
            foreach (CurrencyController::SUPPORTED_CURRENCIES as $currency) {
                $articleData['rek_price_' . $currency] = $productData['price_' . $currency] ?? 0;
                $articleData['retail_price_' . $currency] = $productData['retailPrice_' . $currency] ?? 0;
            }

            // Language fields
            foreach ($languages as $language) {
                $articleData['shop_title_' . $language->language_code] = $productData['title_' . $language->language_code] ?? '';
                $articleData['shop_description_' . $language->language_code] = $productData['description_' . $language->language_code] ?? '';
            }

            // Images
            if (!$skipImages) {
                foreach (($productData['images'] ?? []) as $image) {
                    $articleData['images'][] = $this->apiDomain . '/images/' . ($image['isZoomable'] ? 'zoom' : 'normal') . '/' . $image['filename'];
                }
            }

            dump($articleData);

            // Update the article
            $articleController->update(new Request($articleData), $article);

            die('done');

            // Categories
            if ($productData['categories']) {
                $categoryIDs = $this->importCategories($productData['categories']);

                // Connect the article to the categories
                $article->update([
                    'category_ids' => $categoryIDs,
                ]);
            }
        }

        ConfigController::setConfigs(['wgr_last_article_fetch' => $fetchTime]);
    }

    public function importCategories(array $categories, int $parentID = 0)
    {
        $categoryController = new ArticleCategoryController();

        $categoryIDs = [];

        foreach ($categories as $item) {
            $category = $categoryController->getCategoryByTitle(($item['title_en'] ?? ''), $parentID);

            if ($category) {
                $category = $categoryController->updateCategory($category, $item);
            }
            else {
                $category = $categoryController->createCategory($item, $parentID);
            }

            $categoryIDs[] = $category->id;

            if ($item['children']) {
                $childrenIDs = $this->importCategories($item['children'], $category->id);

                $categoryIDs = array_merge($categoryIDs, $childrenIDs);
            }
        }

        return $categoryIDs;
    }

    /**
     * Updates an article in the WGR API
     * Docs: https://www.reseller.vendora.se/api/docs/#article-set
     *
     * @return void
     */
    public function updateArticle(string $articleNumber, array $data = [])
    {
        $params = array_merge(['articleNumber' => $articleNumber], $data);
        $this->makeRequest('Article.set', $params);
    }

    /**
     * Makes a request to the WGR API and returns the result
     *
     * @param string $method
     * @param array $params
     * @return array
     */
    public function makeRequest(string $method, array $params = []): array
    {
        $request = [
            [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params
            ]
        ];

        $requestBody = json_encode($request);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->apiDomain . '/api/v1/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_USERPWD, $this->apiUsername . ':' . $this->apiPassword);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        list($responseHeaders, $responseBody) = explode("\r\n\r\n", $response, 2);

        $responseData = json_decode($responseBody, true);

        return is_array($responseData) ? $responseData : [];
    }
}
