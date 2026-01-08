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
        action_log('Invoked job method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);

        $this->articleID = $articleID;
        $this->isNew = $isNew;
    }

    public function middleware()
    {
        action_log('Invoked job method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);

        return [new ArticleSyncControl];
    }

    /**
     * Execute the job.
     */
    public function handle(ArticleService $articleService): void
    {
        action_log('Executing job handle method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);

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
