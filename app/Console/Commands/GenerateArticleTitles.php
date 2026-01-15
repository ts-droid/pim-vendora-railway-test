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
            ->where('shop_description_sv', '!=', '')
            ->whereNotNull('brand')
            ->whereNotNull('description')
            ->whereNotNull('shop_description_sv')
            ->where(function($query) {
                $query->whereNull('article_name_sv')
                    ->orWhere('article_name_sv', '')
                    ->orWhereNull('shop_title_sv')
                    ->orWhere('shop_title_sv', '')
                    ->orWhereNull('meta_title_sv')
                    ->orWhere('meta_title_sv', '')
                    ->orWhereNull('meta_description_sv')
                    ->orWhere('meta_description_sv', '')
                    ->orWhereNull('shop_marketing_description_sv')
                    ->orWhere('shop_marketing_description_sv', '')
                    ->orWhereNull('short_description_sv')
                    ->orWhere('short_description_sv', '');
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
