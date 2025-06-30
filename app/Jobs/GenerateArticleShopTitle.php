<?php

namespace App\Jobs;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\TranslationController;
use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateArticleShopTitle implements ShouldQueue
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
        $productData = json_encode($this->article->toArray());

        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode('article_shop_title');

        $rawResponse = $promptController->execute(
            $prompt->id,
            [
                'product_data' => $productData
            ],
            '',
            'gpt-4o'
        );

        if (!$rawResponse) {
            throw new \Exception('AI response is empty.');
        }

        // Decode the JSON response
        $json = $rawResponse;
        $json = str_replace('```json', '', $json);
        $json = str_replace('```', '', $json);

        $response = json_decode($json, true);

        if (!$response || empty($response['title']) || empty($response['short_description'])) {
            throw new \Exception('Invalid response format from AI service.');
        }

        $updateData = [
            'shop_title_sv' => $response['title'],
            'shop_marketing_description_sv' => $response['short_description'],
        ];

        $languages = (new LanguageController())->getAllLanguages();
        $translationController = new TranslationController();

        foreach ($languages as $locale) {
            if ($locale->language_code == 'sv') continue;

            $translations = $translationController->translate([$response['title'], $response['short_description']], 'sv', $locale->language_code, false);

            $updateData['shop_title_' . $locale->language_code] = ($translations[0] ?? '');
            $updateData['shop_marketing_description_' . $locale->language_code] = ($translations[1] ?? '');
        }

        DB::table('articles')
            ->where('id', $this->article->id)
            ->update($updateData);
    }
}
