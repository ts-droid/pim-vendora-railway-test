<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Enums\LaravelQueues;
use App\Jobs\CategorizeArticle;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CategorizeArticles extends Command
{
    use ProvidesCommandLogContext;

    const BATCH_SIZE = 50;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:categorize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting article categorization batch.', $this->commandLogContext([
            'batch_size' => self::BATCH_SIZE,
        ]));

        $articles = Article::where('status', '!=', 'Inactive')
            ->where('is_webshop', '1')
            ->where('google_product_category', '=', 0)
            ->where('shop_description_en', '!=', '')
            ->whereNotNull('shop_description_en')
            ->where(function ($query) {
                $query->whereNull('last_categorize')
                    ->orWhere('last_categorize', '<', Carbon::now()->subDays(2));
            })
            ->limit(self::BATCH_SIZE)
            ->get();

        if (!$articles->count()) {
            action_log('No articles eligible for categorization.', $this->commandLogContext());
            return;
        }

        foreach ($articles as $article) {
            CategorizeArticle::dispatch($article)->onQueue(LaravelQueues::DEFAULT->value);
        }

        action_log('Queued articles for categorization.', $this->commandLogContext([
            'dispatched' => $articles->count(),
        ]));
    }
}
