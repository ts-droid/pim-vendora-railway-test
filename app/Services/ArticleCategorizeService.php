<?php

namespace App\Services;

use App\Models\Article;
use App\Services\AI\AIService;

class ArticleCategorizeService
{
    const MODEL = 'gpt-4o-mini';

    public function categorizeArticle(Article $article)
    {
        $path = storage_path('google_product_categories.txt');
        $googleCategories = file_get_contents($path);

        $system = 'Product description:' . PHP_EOL . ($article->shop_description_en ?? '') . PHP_EOL . PHP_EOL . 'Google Product Categories:' . PHP_EOL . $googleCategories;

        $message = 'You are a strict classification expert trained in the Google Product Taxonomy.
        Your task is to:
        - Read the product description carefully.
        - Select the *most specific and deeply nested* Google Product Category ID that accurately describes the product.
        - Only select a category that fits the description with high confidence.
        - Never respond with a general top-level category if more specific ones are available.

        Instructions:
        - Respond **only** with the category ID (numeric only), nothing else.
        - Do not explain your choice or add any text.

        Be precise and avoid vague or broad classifications.';

        $AIService = new AiService(self::MODEL);
        $response = $AIService->chatCompletion($system, $message, 0);

        dd($response);
    }
}
