<?php

namespace App\Console\Commands;

use App\Enums\LaravelQueues;
use App\Jobs\GenerateShortDescriptionForArticle;
use App\Models\Article;
use Illuminate\Console\Command;

class GenerateMissingShortDescriptions extends Command
{
    const BATCH_SIZE = 50;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'article:generate-missing-short-descriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate short descriptions for articles that are missing them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $articles = Article::where('status', '!=', 'Inactive')
            ->where('shop_description_en', '!=', '')
            ->whereNotNull('shop_description_en')
            ->where(function ($query) {
                $query->whereNull('short_description_en')
                    ->orWhere('short_description_en', '');
            })
            ->limit(self::BATCH_SIZE)
            ->get();

        if (!$articles->count()) {
            return;
        }

        foreach ($articles as $article) {
            GenerateShortDescriptionForArticle::dispatch($article)->onQueue(LaravelQueues::DEFAULT->value);
        }
    }
}
