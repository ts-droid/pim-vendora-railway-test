<?php

namespace App\Listeners;

use App\Events\ArticleStored;
use App\Services\Models\ArticleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleArticleStore implements ShouldQueue
{
    use interactsWithQueue;

    public string $queue = 'article-sync';

    public ArticleService $articleService;

    public function __construct(ArticleService $articleService)
    {
        $this->articleService = $articleService;
    }

    public function handle(ArticleStored $articleStored): void
    {
        $this->articleService->handleStore($articleStored->article);
    }
}
