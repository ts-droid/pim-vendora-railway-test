<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ShortDescriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateShortDescriptionForArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Article $article)
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->article->short_description_en) {
            return; // Short description already exists
        }

        $shortDescriptionService = new ShortDescriptionService();
        $shortDescriptionService->generateArticle($this->article);
    }
}
