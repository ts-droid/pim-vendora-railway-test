<?php

namespace App\Console\Commands;

use App\Http\Controllers\LanguageController;
use App\Jobs\GenerateArticleMetaData;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateArticlesMetaData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meta-data:generate-articles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate meta data for articles missing meta data.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $languages = (new LanguageController())->getAllLanguages();
        $locales = $languages->pluck('language_code');

        $articles = Article::where('is_webshop', '1')
            ->whereIn('status', ['Active', 'NoPurchases'])
            ->where('shop_title_en', '!=', '')
            ->where('shop_title_sv', '!=', '')
            ->whereNotNull('shop_title_en')
            ->whereNotNull('shop_title_sv')
            ->where(function($query) use ($locales) {
                foreach ($locales as $locale) {
                    $query->orWhere('meta_title_' . $locale, '=', '')
                        ->orWhere('meta_title_' . $locale, '=', '0')
                        ->orWhereNull('meta_title_' . $locale);

                    $query->orWhere('meta_description_' . $locale, '=', '')
                        ->orWhere('meta_description_' . $locale, '=', '0')
                        ->orWhereNull('meta_description_' . $locale);
                }
            })
            ->limit(10)
            ->get();

        if (!$articles->count()) return;

        foreach ($articles as $article) {
            // Avoid spamming the job, run max once every 24 hours
            $cacheKey = 'meta-data:generate-articles_' . $article->id;
            $lastActionTime = (int) Cache::get($cacheKey);
            if ($lastActionTime > 0 && (time() - $lastActionTime) < 86400) {
                return;
            }

            // Run the job
            Cache::put($cacheKey, time());

            $job = new GenerateArticleMetaData($article->id);
            $job->handle();
        }
    }
}
