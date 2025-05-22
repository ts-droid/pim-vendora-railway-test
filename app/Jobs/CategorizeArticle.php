<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ArticleCategorizeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CategorizeArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Article $article) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->article->shop_description_en) {
            return;
        }

        $articleCategorizeService = new ArticleCategorizeService();
        $articleCategorizeService->categorizeArticle($this->article);
    }
}
