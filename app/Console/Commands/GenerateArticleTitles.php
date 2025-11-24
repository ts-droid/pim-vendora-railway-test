<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;

class GenerateArticleTitles extends Command
{
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
        $articles = Article::where('status', '!=', 'Inactive')
            ->where('is_webshop', '1')
            ->where('brand', '!=', '')
            ->where('shop_description_en', '!=', '')
            ->whereNotNull('shop_description_en')
            ->where(function($query) {
                $query->whereNull('shop_title_en')
                    ->orWhere('shop_title_en', '')
                    ->orWhereNull('meta_title_en')
                    ->orWhere('meta_title_en', '')
                    ->orWhereNull('meta_description_en')
                    ->orWhere('meta_description_en', '')
                    ->orWhereNull('shop_marketing_description_en')
                    ->orWhere('shop_marketing_description_en', '');
            })
            ->limit(10)
            ->get();

        if (!$articles->count()) {
            return;
        }

        foreach ($articles as $article) {
            \App\Jobs\GenerateArticleTitles::dispatch($article);
        }
    }
}
