<?php

namespace App\Listeners;

use App\Events\ArticleUpdated;
use App\Services\Models\ArticleService;

class HandleArticleUpdate
{
    private ArticleService $articleService;

    public function __construct(ArticleService $articleService)
    {
        $this->articleService = $articleService;
    }

    public function handle(ArticleUpdated $articleUpdated): void
    {
        $this->articleService->handleUpdate($articleUpdated->article, $articleUpdated->changes);
    }
}
