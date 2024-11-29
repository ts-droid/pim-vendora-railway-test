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
use Illuminate\Support\Facades\DB;

class ArticleService
{
    public function handleStore(Article $article): void
    {
        try {
            // Create in Visma.net
            $vismaNetArticleService = new VismaNetArticleService();
            $vismaNetArticleService->createArticle($article);

            // Create in WGR
            $wgrArticleService = new WgrArticleService();
            $wgrArticleService->createArticle($article);

            $this->resetException($article);
        } catch (\Exception $e) {
            $this->handleException($article, $e);
        }
    }

    public function handleUpdate(Article $article): void
    {
        try {
            // Push update to Visma.net
            $vismaNetArticleService = new VismaNetArticleService();
            $vismaNetArticleService->updateArticle($article);

            // Push update to WGR
            $wgrArticleService = new WgrArticleService();
            $wgrArticleService->updateArticle($article);

            $this->resetException($article);
        } catch (\Exception $e) {
            $this->handleException($article, $e);
        }
    }

    private function handleException(Article $article, \Exception $e)
    {
        DB::table('articles')
            ->where('id', '=', $article->id)
            ->update([
                'last_sync_exception' => $e->getMessage()
            ]);

        throw new \Exception($e->getMessage());
    }

    private function resetException(Article $article)
    {
        DB::table('articles')
            ->where('id', '=', $article->id)
            ->update([
                'last_sync_exception' => ''
            ]);
    }
}
