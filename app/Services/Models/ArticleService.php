<?php

namespace App\Services\Models;

use App\Enums\ArticleStatus;
use App\Http\Controllers\ArticleCategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\WgrController;
use App\Models\Article;
use App\Services\VismaNet\VismaNetArticleService;
use App\Services\WGR\WgrArticleService;

class ArticleService
{
    public function handleStore(Article $article): void
    {
        if (!str_contains($article->article_number, 'test')) {
            return;
        }

        // Create in Visma.net
        // $vismaNetArticleService = new VismaNetArticleService();
        // $vismaNetArticleService->createArticle($article);

        // Create in WGR
        $wgrArticleService = new WgrArticleService();
        $wgrArticleService->createArticle($article);
    }

    public function handleUpdate(Article $article, array $changes): void
    {
        if (!str_contains($article->article_number, 'test')) {
            return;
        }

        if (!$changes) {
            return;
        }

        // Push update to Visma.net
        // $vismaNetArticleService = new VismaNetArticleService();
        // $vismaNetArticleService->updateArticle($article);

        // Push update to WGR
        $wgrArticleService = new WgrArticleService();
        $wgrArticleService->updateArticle($article);
    }
}
