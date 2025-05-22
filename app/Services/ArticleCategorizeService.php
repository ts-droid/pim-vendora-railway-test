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

        $message = 'You are a strict classificator. Read through the product description and give me the most relevant Google Product Category.
        Respond only with the category id.';

        $AIService = new AiService(self::MODEL);
        $response = $AIService->chatCompletion($system, $message, 0);

        dd($response);
    }
}
