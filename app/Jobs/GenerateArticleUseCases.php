<?php

namespace App\Jobs;

use App\Http\Controllers\PromptController;
use App\Http\Controllers\RawDataController;
use App\Models\Article;
use App\Models\ArticleMetaData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateArticleUseCases implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Article $article,
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode('article_use_cases');

        $rawResponse = $promptController->execute(
            $prompt->id,
            [
                'raw_data' => RawDataController::getArticleRaw($this->article, true, true, true, 'en')
            ]
        );

        $response = json_decode($rawResponse, true);

        if (!is_array($response) || !is_array($response['designed_for']) || !is_array($response['use_cases'])) {
            throw new \Exception('Invalid response or format from AI prompt');
        }

        // Create designed for
        foreach ($response['designed_for'] as $item) {
            ArticleMetaData::create([
                'article_id' => $this->article->id,
                'type' => 'designed_for',
                'value_en' => $item
            ]);
        }

        // Create use cases
        foreach ($response['use_cases'] as $item) {
            ArticleMetaData::create([
                'article_id' => $this->article->id,
                'type' => 'use_cases',
                'value_en' => $item
            ]);
        }
    }
}
