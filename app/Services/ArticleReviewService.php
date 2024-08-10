<?php

namespace App\Services;

use App\Http\Controllers\WgrController;
use App\Models\ArticleReview;

class ArticleReviewService
{
    public function store(array $data): ?ArticleReview
    {
        $articleNumber = $data['article_number'];
        $name = $data['name'];
        $content = $data['content'];
        $ip = $data['ip'];

        // Check if the review already exists
        $reviewExists = ArticleReview::where('article_number', $articleNumber)
            ->where('name', $name)
            ->where('content', $content)
            ->where('ip', $ip)
            ->exists();

        if ($reviewExists) {
            return null;
        }

        // Create the review
        $articleReview = ArticleReview::create([
            'article_number' => (string) $articleNumber,
            'name' => (string) $name,
            'content' => (string) $content,
            'ip' => (string) $ip,
            'stars' => (int) $data['stars'],
            'default_language' => (string) $data['default_language'],
            'published_at' => (string) $data['published_at'],
        ]);

        // Create review in external services
        $wgrController = new WgrController();

        $wgrArticle = $wgrController->getArticle($articleNumber);

        $wgrController->makeRequest('Reviews.create', [
            'name' => $articleReview->name,
            'txt' => $articleReview->content,
            'reviewDate' => $articleReview->published_at,
            'ip' => $articleReview->ip,
            'productID' => (int) ($wgrArticle['productId'] ?? 0),
            'stars' => $articleReview->stars,
            'languageCode' => $articleReview->default_language,
        ]);

        return $articleReview;
    }

    public function update(ArticleReview $articleReview, array $data): void
    {
        $articleReview->update($data);

        // TODO: Update review in external services

    }

    public function delete(ArticleReview $articleReview): void
    {
        $articleReview->delete();

        // TODO: Delete review in external services

    }
}
