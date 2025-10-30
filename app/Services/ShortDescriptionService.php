<?php

namespace App\Services;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\TranslationController;
use App\Models\Article;
use Illuminate\Support\Facades\DB;

class ShortDescriptionService
{
    const MODEL = 'gpt-4o';

    public function generateArticle(Article $article, $returnOnly = false): array
    {
        $promptController = new PromptController();

        $prompt = $promptController->getBySystemCode('article_short_description');

        $rawResponse = $promptController->execute(
            $prompt->id,
            [
                'product_description' => ($article->shop_description_en ?? ''),
            ],
            '',
            'gpt-4o'
        );

        if (!$rawResponse) {
            return [
                'success' => false,
                'error_message' => 'No response from AI service.',
            ];
        }

        $rawResponse = preg_replace('/\r|\n/', '', $rawResponse);
        $points = explode('- ', $rawResponse);
        $points = array_filter($points);

        if (count($points) < 3) {
            return [
                'success' => false,
                'error_message' => 'Short description must contain at least 3 points.',
            ];
        }

        $shortDescription = '';
        foreach ($points as $point) {
            $shortDescription .= '<li>' . $point . '</li>';
        }
        $shortDescription = '<ul>' . $shortDescription . '</ul>';


        // Translate the short description
        $updates = [
            'short_description_en' => $shortDescription,
        ];

        $languages = (new LanguageController())->getAllLanguages();
        $translationController = new TranslationController();

        foreach ($languages as $locale) {
            if ($locale->language_code === 'en') continue;

            $translations = $translationController->translate([$shortDescription], 'en', $locale->language_code, true);
            $updates['short_description_' . $locale->language_code] = $translations[0] ?? '';
        }


        // Update the article with the generated short description
        if (!$returnOnly) {
            DB::table('articles')
                ->where('id', $article->id)
                ->update($updates);
        }

        return [
            'success' => true,
            'error_message' => null,
            'updates' => $updates,
        ];
    }
}
