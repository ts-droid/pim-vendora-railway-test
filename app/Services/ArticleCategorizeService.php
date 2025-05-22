<?php

namespace App\Services;

use App\Models\Article;
use App\Services\AI\AIService;

class ArticleCategorizeService
{
    const MODEL = 'gpt-4o';

    public function categorizeArticle(Article $article)
    {
        $system = 'Product description:' . PHP_EOL . ($article->shop_description_en ?? '');

        $message = 'You are a e-commerce assistant. Read through the product description and give me the most relevant Google Product Category.
        Respond only with the category name. The category name must be in English.';

        $AIService = new AiService(self::MODEL);
        $response = $AIService->chatCompletion($system, $message);

        dd($response);
    }
}
