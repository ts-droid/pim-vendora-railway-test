<?php

namespace App\Console\Commands;

use App\Enums\LaravelQueues;
use App\Jobs\GenerateArticleShopTitle;
use App\Models\Article;
use Illuminate\Console\Command;

class GenerateArticleShopData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-article-shop-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate shop title and marketing description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $articles = Article::whereIn('status', ['Active', 'NoPurchases'])
            ->where(function($query) {
                $query->where('shop_title_sv', '=', '')
                    ->orWhereNull('shop_title_sv')
                    ->orWhere('shop_title_en', '=', '')
                    ->orWhereNull('shop_title_en')
                    ->orWhere('shop_marketing_description_sv', '=', '')
                    ->orWhereNull('shop_marketing_description_sv')
                    ->orWhere('shop_marketing_description_en', '=', '')
                    ->orWhereNull('shop_marketing_description_en');
            })
            ->limit(50)
            ->get();

        if (!$articles || !$articles->count()) {
            return;
        }

        foreach ($articles as $article) {
            $job = new GenerateArticleShopTitle($article);
            $job->handle();
        }
    }
}
