<?php

namespace App\Services;

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
        return ArticleReview::create([
            'article_number' => $articleNumber,
            'name' => $name,
            'content' => $content,
            'ip' => $ip,
            'stars' => (int) $data['stars'],
            'default_language' => $data['default_language'],
            'published_at' => $data['published_at'],
        ]);
    }

    public function update(ArticleReview $articleReview, array $data): void
    {
        $articleReview->update($data);
    }

    public function delete(ArticleReview $articleReview): void
    {
        $articleReview->delete();
    }
}
