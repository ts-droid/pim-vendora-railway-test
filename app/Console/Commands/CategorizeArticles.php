<?php

namespace App\Console\Commands;

use App\Enums\LaravelQueues;
use App\Jobs\CategorizeArticle;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CategorizeArticles extends Command
{
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
        $articles = Article::where('status', '=', 'Active')
            ->where('shop_description_en', '!=', '')
            ->whereNotNull('shop_description_en')
            ->where(function ($query) {
                $query->whereNull('last_categorize')
                    ->orWhere('last_categorize', '<', Carbon::now()->subDays(30));
            })
            ->limit(self::BATCH_SIZE)
            ->get();

        if (!$articles->count()) {
            return;
        }

        foreach ($articles as $article) {
            CategorizeArticle::dispatch($article)->onQueue(LaravelQueues::DEFAULT->value);

            DB::table('articles')
                ->where('id', $article->id)
                ->update(['last_categorize' => Carbon::now()]);
        }
    }
}
