<?php

namespace App\Services;

use App\Models\Article;
use App\Services\AI\AIService;
use App\Services\AI\OpenAIService;

class ArticleCategorizeService
{
    const COMPLETION_MODEL = 'gpt-4o-mini';

    const EMBEDDINGS_MODEL = 'text-embedding-3-small';
    CONST EMBEDDINGS_PATH = 'app/google_product_categories_embeddings.json';

    public function categorizeArticle(Article $article)
    {
        $this->generateEmbeddings();

        $productDescription = $article->shop_description_en ?? '';

        $openAiService = new OpenAIService(self::EMBEDDINGS_MODEL);

        $productEmbedding = $openAiService->getEmbedding($productDescription);

        $categories = json_decode(file_get_contents(storage_path(self::EMBEDDINGS_PATH)), true);

        $similarities = [];

        foreach ($categories as $category) {
            $similarity = $this->cosineSimilarity($productEmbedding, $category['embedding']);
            $similarities[] = [
                'id' => $category['id'],
                'name' => $category['name'],
                'similarity' => $similarity,
            ];
        }

        // Sort by best match
        usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Get the top 5 categories
        $topCategories = array_slice($similarities, 0, 5);

        $relevantCategories = collect($topCategories)->map(function ($item) {
            return $item['id'] . ' - ' . $item['name'];
        })->implode(PHP_EOL);


        $system = 'Product description: ' . PHP_EOL . ($article->shop_description_en ?? '') . PHP_EOL . PHP_EOL . 'Possible Google Product Categories:' . PHP_EOL . $relevantCategories;

        $message = 'You are a product classification expert.

        You will receive a product description and a short list of candidate Google Product Categories. Your task is to select the **single most accurate and specific** category from this list.

        Each category is formatted as: [category_id] - [category_name].

        Choose the category that best fits the product **based on its use, function, or type**, not just based on keywords.

        Respond with only the category ID (number only). Do not include any other text.';

        $AIService = new AiService(self::COMPLETION_MODEL);
        $response = $AIService->chatCompletion($system, $message, 0);

        dd($response);
    }

    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = array_sum(array_map(fn($a, $b) => $a * $b, $vec1, $vec2));
        $magnitude1 = sqrt(array_sum(array_map(fn($a) => $a ** 2, $vec1)));
        $magnitude2 = sqrt(array_sum(array_map(fn($b) => $b ** 2, $vec2)));

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    private function generateEmbeddings()
    {
        $embeddingsPath = storage_path(self::EMBEDDINGS_PATH);

        if (file_exists($embeddingsPath)) {
            return;
        }

        $openAiService = new OpenAIService(self::EMBEDDINGS_MODEL);

        $lines = file(storage_path('google_product_categories.txt'));
        $categories = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\d+)\s*-\s*(.+)$/', $line, $matches)) {
                $categories[] = [
                    'id' => $matches[1],
                    'name' => trim($matches[2]),
                ];
            }
        }

        foreach ($categories as &$category) {
            $category['embedding'] = $openAiService->getEmbedding($category['name']);
        }

        file_put_contents($embeddingsPath, json_encode($categories));
    }
}
