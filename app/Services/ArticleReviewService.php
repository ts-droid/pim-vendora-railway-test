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
        $wgrID = (int) ($data['wgr_id'] ?? 0);

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
            'wgr_id' => $wgrID,
        ]);

        // Create review in external services
        if (!$wgrID) {
            $wgrController = new WgrController();

            $wgrArticle = $wgrController->getArticle($articleNumber);

            $response = $wgrController->makeRequest('Reviews.create', [
                'name' => $articleReview->name,
                'txt' => $articleReview->content,
                'reviewDate' => $articleReview->published_at,
                'ip' => $articleReview->ip,
                'productID' => (int) ($wgrArticle['productId'] ?? 0),
                'stars' => $articleReview->stars,
                'languageCode' => $articleReview->default_language,
            ]);

            $articleReview->update([
                'wgr_id' => ($response[0]['result']['id'] ?? 0)
            ]);
        }

        return $articleReview;
    }

    public function update(ArticleReview $articleReview, array $data): void
    {
        $articleReview->update($data);

        // Update review in external services
        if ($articleReview->wgr_id) {
            $wgrController = new WgrController();

            $wgrArticle = $wgrController->getArticle($articleReview->article_number);

            $wgrController->makeRequest('Reviews.set', [
                'id' => $articleReview->wgr_id,
                'name' => $articleReview->name,
                'txt' => $articleReview->content,
                'reviewDate' => $articleReview->published_at,
                'ip' => $articleReview->ip,
                'productID' => (int) ($wgrArticle['productId'] ?? 0),
                'stars' => $articleReview->stars,
                'languageCode' => $articleReview->default_language,
            ]);
        }
    }

    public function delete(ArticleReview $articleReview): void
    {
        $articleReview->delete();

        // Delete review in external services
        if ($articleReview->wgr_id) {
            $wgrController = new WgrController();

            $wgrController->makeRequest('Reviews.delete', [
                'id' => $articleReview->wgr_id
            ]);
        }
    }
}
