<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ArticleCategorizeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CategorizeArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Article $article, protected bool $returnOnly = false) {}

    /**
     * Execute the job.
     */
    public function handle(): int
    {
        if (!$this->article->shop_description_en) {
            return 0;
        }

        $articleCategorizeService = new ArticleCategorizeService();
        $categoryID = $articleCategorizeService->categorizeArticle($this->article, $this->returnOnly);

        if (!$this->returnOnly) {
            DB::table('articles')
                ->where('id', $this->article->id)
                ->update(['last_categorize' => Carbon::now()]);
        }

        return $categoryID;
    }
}
