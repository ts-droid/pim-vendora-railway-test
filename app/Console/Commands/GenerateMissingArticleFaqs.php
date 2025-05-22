<?php

namespace App\Console\Commands;

use App\Enums\LaravelQueues;
use App\Jobs\GenerateFaqForArticle;
use App\Models\Article;
use Illuminate\Console\Command;

class GenerateMissingArticleFaqs extends Command
{
    const BATCH_SIZE = 3;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faq:generate-missing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate FAQ for articles that are missing FAQ entries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $articles = Article::where('status', '=', 'Active')
            ->where('shop_title_en', '!=', '')
            ->whereNotNull('shop_title_en')
            ->doesntHave('faqEntries')
            ->limit(self::BATCH_SIZE)
            ->get();

        if (!$articles->count()) {
            return;
        }

        foreach ($articles as $article) {
            GenerateFaqForArticle::dispatch($article)->onQueue(LaravelQueues::DEFAULT->value);
        }
    }
}
