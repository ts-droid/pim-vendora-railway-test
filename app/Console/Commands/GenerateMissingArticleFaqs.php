<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Models\Article;
use App\Services\FaqService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMissingArticleFaqs extends Command
{
    use ProvidesCommandLogContext;

    const BATCH_SIZE = 1000;

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
                    ->orWhere('last_faq_generation', '<', Carbon::now()->subDays(14));
            })
            ->doesntHave('faqEntries')
            ->limit(self::BATCH_SIZE)
            ->get();

        $faqService = new FaqService();
        $faqService->run($articles);
    }
}
