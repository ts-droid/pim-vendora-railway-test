<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\FaqService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateFaqForArticle implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Article $article)
    {
        action_log('Invoked job method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);
    }

    public function uniqueId()
    {
        return md5(json_encode([
            'article_id' => $this->article->id
        ]));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        return; // TODO: Enable this after debugging

        action_log('Executing job handle method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);

        if ($this->article->faqEntries()->exists()) {
            return;
        }

        DB::table('articles')
            ->where('id', $this->article->id)
            ->update(['last_faq_generation' => Carbon::now()]);

        $faqService = new FaqService();
        $faqService->generateArticleFAQ($this->article);
    }
}
