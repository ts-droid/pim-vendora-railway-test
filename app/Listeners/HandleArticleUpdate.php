<?php

namespace App\Listeners;

use App\Models\Article;
use App\Services\Models\ArticleService;

class HandleArticleUpdate
{
    private ArticleService $articleService;

    public function __construct(ArticleService $articleService)
    {
        $this->articleService = $articleService;
    }

    public function handle(Article $article, array $changes): void
    {
        $this->articleService->handleUpdate($article, $changes);
    }
}
