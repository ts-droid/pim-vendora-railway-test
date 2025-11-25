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

class GenerateArticleTitles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Article $article
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode('article_titles');

        if (!$this->article->brand || !$this->article->shop_title_en || !$this->article->shop_description_en) {
            throw new \Exception('Missing required article data.');
        }

        $faqEntries = [];
        foreach ($this->article->faqEntries as $entry) {
            $faqEntries[] = 'Question: ' . $entry->question_en;
            $faqEntries[] = 'Answer: ' . $entry->answer_en;
            $faqEntries[] = '';
        }

        $rawResponse = $promptController->execute(
            $prompt->id,
            [
                'brand_name' => $this->article->brand ?: '',
                'current_title' => $this->article->shop_title_en ?: $this->article->description ?: '',
                'current_description' => $this->article->shop_description_en ?: '',
                'marketing_description' => $this->article->marketing_description_en ?: '',
                'short_description' => strip_tags($this->article->short_description_en ?: ''),
                'faq' => implode(PHP_EOL, $faqEntries),
            ]
        );

        if (!$rawResponse) {
            throw new \Exception('No response from AI service.');
        }

        $json = $rawResponse;
        $json = str_replace('```json', '', $json);
        $json = str_replace('```', '', $json);
        $response = json_decode($json, true);

        $color = $response['color'] ?? null;
        $shortTitle = $response['short_title'] ?? null;
        $longTitle = $response['long_title'] ?? null;
        $metaTitle = $response['meta_title'] ?? null;
        $metaDescription = $response['meta_description'] ?? null;
        $premiumIntroText = $response['premium_introtext'] ?? null;
        $sellingPoints = $response['selling_points'] ?? [];

        if (!$color || !$shortTitle || !$longTitle || !$metaTitle || !$metaDescription || !$premiumIntroText || empty($sellingPoints) || count($sellingPoints) !== 3) {
            throw new \Exception('Invalid response format from AI service.');
        }

        $sellingPoints = [
            'en' => $sellingPoints
        ];

        // Translate and save each value
        $updates = [
            'color_en' => $color,
            'shop_title_en' => $longTitle,
            'meta_title_en' => $metaTitle,
            'meta_description_en' => $metaDescription,
            'shop_marketing_description_en' => $premiumIntroText,

            // This one does not need translation
            'description' => $shortTitle,
        ];

        $translationController = new TranslationController();
        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $language) {
            if ($language->language_code == 'en') continue;

            $updates['color_' . $language->language_code] = $translationController->translate([$updates['color_en']], 'en', $language->language_code)[0];
            $updates['shop_title_' . $language->language_code] = $translationController->translate([$updates['shop_title_en']], 'en', $language->language_code)[0];
            $updates['meta_title_' . $language->language_code] = $translationController->translate([$updates['meta_title_en']], 'en', $language->language_code)[0];
            $updates['meta_description_' . $language->language_code] = $translationController->translate([$updates['meta_description_en']], 'en', $language->language_code)[0];
            $updates['shop_marketing_description_' . $language->language_code] = $translationController->translate([$updates['shop_marketing_description_en']], 'en', $language->language_code)[0];

            $sellingPoints[$language->language_code] = $translationController->translate($sellingPoints['en'], 'en', $language->language_code);
        }

        foreach ($sellingPoints as $languageCode => $points) {
            $html = '<ul>';
            foreach ($points as $point) {
                $html .= '<li>' . $point . '</li>';
            }
            $html .= '</ul>';

            $updates['short_description_' . $languageCode] = $html;
        }

        if (count($updates) > 0) {
            $this->article->update($updates);
        }
    }
}
