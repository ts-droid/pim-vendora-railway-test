<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Enums\LaravelQueues;
use App\Models\Article;
use Illuminate\Console\Command;

class GenerateArticleTitles extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'article:generate-titles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate titles for articles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting article title generation batch.', $this->commandLogContext());

        $articles = Article::where('status', '!=', 'Inactive')
            ->where('is_webshop', '1')
            ->where('brand', '!=', '')
            ->where('description', '!=', '')
            ->where('shop_description_en', '!=', '')
            ->whereNotNull('brand')
            ->whereNotNull('description')
            ->whereNotNull('shop_description_en')
            ->where(function($query) {
                $query->whereNull('article_name_en')
                    ->orWhere('article_name_en', '')
                    ->orWhereNull('shop_title_en')
                    ->orWhere('shop_title_en', '')
                    ->orWhereNull('meta_title_en')
                    ->orWhere('meta_title_en', '')
                    ->orWhereNull('meta_description_en')
                    ->orWhere('meta_description_en', '')
                    ->orWhereNull('shop_marketing_description_en')
                    ->orWhere('shop_marketing_description_en', '')
                    ->orWhereNull('short_description_en')
                    ->orWhere('short_description_en', '');
            })
            ->limit(10)
            ->get();

        if (!$articles->count()) {
            action_log('No articles found for title generation.', $this->commandLogContext());
            return;
        }

        foreach ($articles as $article) {
            \App\Jobs\GenerateArticleTitles::dispatch($article)->onQueue(LaravelQueues::GENERATION->value);
        }

        action_log('Queued articles for title generation.', $this->commandLogContext([
            'queued_articles' => $articles->count(),
        ]));
    }
}
