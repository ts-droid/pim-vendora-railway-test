<?php

namespace App\Services;

use App\Models\Article;
use App\Services\AI\AIService;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\DB;

class ArticleCategorizeService
{
    const COMPLETION_MODEL = 'gpt-4o-mini';

    const EMBEDDINGS_MODEL = 'text-embedding-3-small';
    CONST EMBEDDINGS_PATH = 'app/google_product_categories_embeddings.json';

    public function categorizeArticle(Article $article, bool $returnOnly = false)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

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

        $system = 'You are a product classification expert.

        You will receive a product description and a short list of candidate Google Product Categories. Your task is to select the **single most accurate and specific** category from this list.

        Each category is formatted as: [category_id] - [category_name].

        Choose the category that best fits the product **based on its use, function, or type**, not just based on keywords.

        Respond with only the category ID (number only). Do not include any other text.';

        $message = 'Product description: ' . PHP_EOL . ($article->shop_description_en ?? '') . PHP_EOL . PHP_EOL . 'Candidate Google Product Categories:' . PHP_EOL . $relevantCategories . PHP_EOL . PHP_EOL . 'Which category best matches the product?';

        $AIService = new AiService();
        $response = $AIService->chatCompletion($system, $message);

        $categoryID = (int) $response;

        if (!$categoryID) {
            return 0;
        }

        if (!$returnOnly) {
            DB::table('articles')
                ->where('id', $article->id)
                ->update(['google_product_category' => $categoryID]);
        }

        return $categoryID;
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
