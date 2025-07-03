<?php

namespace App\Console\Commands;

use App\Enums\LaravelQueues;
use App\Jobs\GenerateFaqForArticle;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateMissingArticleFaqs extends Command
{
    const BATCH_SIZE = 50;

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
        $articles = Article::where('status', '!=', 'Inactive')
            ->where('shop_description_en', '!=', '')
            ->whereNotNull('shop_description_en')
            ->where(function ($query) {
                $query->whereNull('last_faq_generation')
                    ->orWhere('last_faq_generation', '<', Carbon::now()->subDays(30));
            })
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
