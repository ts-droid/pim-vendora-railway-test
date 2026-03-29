<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\FaqService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateFaqForArticle implements ShouldQueue
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

        $faqService = new FaqService();
        $faqService->generateArticleFAQ($this->article);

        DB::table('articles')
            ->where('id', $this->article->id)
            ->update(['last_faq_generation' => Carbon::now()]);
    }
}
