<?php

namespace App\Jobs;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\RawDataController;
use App\Http\Controllers\TranslationController;
use App\Models\Article;
use App\Utilities\ArticleTitleUtility;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateArticleTitles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const BASE_LANGUAGE = 'sv';

    const FIELD_MAPPING = [
        'article_name' => 'description',
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Article $article
    )
    {}

    public function handle(): array
    {
        if (!$this->article->brand || !$this->article->shop_title_sv || !$this->article->shop_description_sv) {
            throw new \Exception('Missing required article data.');
        }

        $allUpdates = [];

        $updates = $this->handleShortTitle();
        $allUpdates = array_merge($allUpdates, $updates);

        $updates = $this->handleColor();
        $allUpdates = array_merge($allUpdates, $updates);

        $updates = $this->handleLongTitle();
        $allUpdates = array_merge($allUpdates, $updates);

        $updates = $this->handlePremiumIntroText();
        $allUpdates = array_merge($allUpdates, $updates);

        $updates = $this->handleSellingPoints();
        $allUpdates = array_merge($allUpdates, $updates);

        $updates = $this->handleMetaTitle();
        $allUpdates = array_merge($allUpdates, $updates);

        $updates = $this->handleMetaDescription();
        $allUpdates = array_merge($allUpdates, $updates);

        return $allUpdates;
    }

    public function handlePremiumIntroText(bool $returnOnly = false): array
    {
        $response = $this->executePrompt('article_titles_premium_intro_text', ['shop_marketing_description'], true);

        $updates = [
            'shop_marketing_description' . self::BASE_LANGUAGE => $response['shop_marketing_description'],
        ];
        $updates = $this->translateValues($updates, ['shop_marketing_description']);

        if (!$returnOnly) {
            $this->update($updates);
        }

        return $updates;
    }

    public function handleSellingPoints(bool $returnOnly = false): array
    {
        $response = $this->executePrompt('article_titles_selling_points', ['short_description'], true);

        $sellingPoints = [
            self::BASE_LANGUAGE => $response['short_description']
        ];

        $translationController = new TranslationController();
        $languages = (new LanguageController())->getAllLanguages();
        foreach ($languages as $language) {
            if ($language->language_code == self::BASE_LANGUAGE) continue;

            $sellingPoints[$language->language_code] = $translationController->translate($sellingPoints[self::BASE_LANGUAGE], self::BASE_LANGUAGE, $language->language_code);
        }

        $updates = [];
        foreach ($sellingPoints as $languageCode => $points) {
            $html = '<ul>';
            foreach ($points as $point) {
                $html .= '<li>' . $point . '</li>';
            }
            $html .= '</ul>';

            $updates['short_description_' . $languageCode] = $html;
        }

        if (!$returnOnly) {
            $this->update($updates);
        }

        return $updates;
    }

    public function handleMetaTitle(bool $returnOnly = false): array
    {
        $response = $this->executePrompt('article_titles_meta_title', ['meta_title'], true);

        $updates = [
            'meta_title_' . self::BASE_LANGUAGE => $response['meta_title'],
        ];
        $updates = $this->translateValues($updates, ['meta_title']);

        if (!$returnOnly) {
            $this->update($updates);
        }

        return $updates;
    }

    public function handleMetaDescription(bool $returnOnly = false): array
    {
        $response = $this->executePrompt('article_titles_meta_description', ['meta_description'], true);

        $updates = [
            'meta_description_' . self::BASE_LANGUAGE => $response['meta_description'],
        ];
        $updates = $this->translateValues($updates, ['meta_description']);

        if (!$returnOnly) {
            $this->update($updates);
        }

        return $updates;
    }

    public function handleLongTitle(bool $returnOnly = false): array
    {
        $response = $this->executePrompt('article_titles_long_title', ['shop_title'], true);

        $updates = ['shop_title' . self::BASE_LANGUAGE => $response['shop_title']];
        $updates = $this->translateValues($updates, ['shop_title']);

        if (!$returnOnly) {
            $this->update($updates);
        }

        return $updates;
    }

    public function handleShortTitle(bool $returnOnly = false): array
    {
        $response = $this->executePrompt('article_titles_short_title', ['article_name'], false, 'en');
        $updates = ['description' => $response['article_name']];

        if (!$returnOnly) {
            $this->update($updates);
            ArticleTitleUtility::translateTitles($this->article);
        }

        return $updates;
    }

    public function handleColor(bool $returnOnly = false): array
    {
        $response = $this->executePrompt('article_titles_color', ['color'], true);

        $updates = ['color_' . self::BASE_LANGUAGE => mb_ucfirst($response['color'])];
        $updates = $this->translateValues($updates, ['color']);

        if (!$returnOnly) {
            $this->update($updates);
        }

        return $updates;
    }

    private function update(array $updates): void
    {
        if (count($updates) > 0) {
            $mappedUpdates = [];
            foreach ($updates as $key => $value) {
                foreach (self::FIELD_MAPPING as $old => $new) {
                    $key = str_replace($old, $new, $key);
                }

                $mappedUpdates[$key] = $value;
            }

            $this->article->update($mappedUpdates);
        }
    }

    private function translateValues(array $array, array $keys): array
    {
        $translationController = new TranslationController();
        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $language) {
            if ($language->language_code == self::BASE_LANGUAGE) continue;

            foreach ($keys as $key) {
                $array[$key . '_' . $language->language_code] = $translationController->translate([$array[$key . '_' . self::BASE_LANGUAGE]], self::BASE_LANGUAGE, $language->language_code)[0];
            }
        }

        return $array;
    }

    private function executePrompt(string $systemCode, array $arrayKeys, bool $includeShortTitle = false, string $locale = 'sv'): array
    {
        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode($systemCode);

        $rawResponse = $promptController->execute(
            $prompt->id,
            ['raw_data' => RawDataController::getArticleRaw($this->article, $includeShortTitle, false, false, $locale)]
        );

        if (!$rawResponse) throw new \Exception('Empty response from AI service.');

        $response = $this->getJsonResponse($rawResponse);

        $structuredResponse = [];
        foreach ($arrayKeys as $key) {
            if (!isset($response[$key])) throw new \Exception('Invalid response from AI service.');

            $structuredResponse[$key] = $response[$key];
        }

        return $structuredResponse;
    }

    private function getJsonResponse(string $rawResponse): array
    {
        $json = $rawResponse;
        $json = str_replace('```json', '', $json);
        $json = str_replace('```', '', $json);
        return json_decode($json, true) ?: [];
    }
}
