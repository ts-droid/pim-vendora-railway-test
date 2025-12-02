<?php

namespace App\Jobs;

use App\Jobs\Middleware\ArticleSyncControl;
use App\Models\Article;
use App\Services\Models\ArticleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $articleID;
    private bool $isNew;

    /**
     * Create a new job instance.
     */
    public function __construct(int $articleID, bool $isNew = false)
    {
        $this->articleID = $articleID;
        $this->isNew = $isNew;
    }

    public function middleware()
    {
        return [new ArticleSyncControl];
    }

    /**
     * Execute the job.
     */
    public function handle(ArticleService $articleService): void
    {
        $article = Article::where('id', '=', $this->articleID)->first();
        if (!$article) {
            return;
        }

        if ($this->isNew) {
            $articleService->handleStore($article);
        }
        else {
            $articleService->handleUpdate($article);
        }

        DB::table('articles')->where('id', $article->id)->update(['is_syncing' => 0]);
    }
}
