<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class RelatedArticlesService
{
    public function connectGroup(array $identifiers, bool $byArticleNumber): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $articles = $this->fetchArticles($identifiers, $byArticleNumber);

        if ($articles->count() < 2) {
            return;
        }

        DB::transaction(function () use ($articles) {
            $pairs = $this->allPairs($articles->pluck('id')->all());

            $now = now();
            $rows = [];

            foreach ($pairs as [$a, $b]) {
                if ($a === $b) continue;

                $rows[] = [
                    'parent_article_id' => $a,
                    'child_article_id' => $b,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $rows[] = [
                    'parent_article_id' => $b,
                    'child_article_id' => $a,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                DB::table('related_articles')->upsert(
                    $rows,
                    ['parent_article_id', 'child_article_id'],
                    ['updated_at']
                );
            }
        });
    }

    public function disconnectSubset(array $identifiers, bool $byArticleNumber = false): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $articles = $this->fetchArticles($identifiers, $byArticleNumber);
        if ($articles->isEmpty()) return;

        $ids = $articles->pluck('id')->all();

        DB::transaction(function () use ($ids) {
            DB::table('related_articles')
                ->whereIn('parent_article_id', $ids)
                ->orWhereIn('child_article_id', $ids)
                ->delete();
        });
    }

    public function syncGroup(array $identifiers, bool $byArticleNumber = false): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $articles = $this->fetchArticles($identifiers, $byArticleNumber);
        if ($articles->count() < 2) {
            $this->disconnectSubset($identifiers, $byArticleNumber);
            return;
        }

        $ids = $articles->pluck('id')->all();

        DB::transaction(function () use ($ids, $articles) {
            DB::table('related_articles')
                ->whereIn('parent_article_id', $ids)
                ->whereIn('child_article_id', $ids)
                ->delete();

            $this->connectGroup($ids, false);
        });
    }


    private function fetchArticles(array $identifiers, bool $byArticleNumber): Collection
    {
        $identifiers = array_values(array_unique(array_filter($identifiers)));

        return $byArticleNumber
            ? Article::query()->whereIn('article_number', $identifiers)->get(['id', 'article_number'])
            : Article::query()->whereIn('id', $identifiers)->get(['id', 'article_number']);
    }

    private function allPairs(array $ids): array
    {
        $pairs = [];
        $n = count($ids);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $pairs[] = [$ids[$i], $ids[$j]];
            }
        }
        return $pairs;
    }
}
