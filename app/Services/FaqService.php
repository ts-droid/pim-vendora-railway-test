<?php

namespace App\Services;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\TranslationController;
use App\Models\Article;
use App\Models\ArticleFaqEntry;
use App\Services\AI\AIService;
use App\Services\AI\OpenAIService;

class FaqService
{
    public function generateArticleFAQ(Article $article): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $promptController = new PromptController();

        $generatePrompt = $promptController->getBySystemCode('faq_articles_generate');
        $validatePrompt = $promptController->getBySystemCode('faq_articles_validate');

        $rawResponse = $promptController->execute(
            $generatePrompt->id,
            ['product_description' => ($article->shop_description_en ?? '')]
        );

        if (!$rawResponse) {
            return ['success' => false, 'error_message' => 'No response from AI service (generation).'];
        }

        $validationResponse = $promptController->execute(
            $validatePrompt->id,
            [
                'product_description' => ($article->shop_description_en ?? ''),
                'faq_json' => $rawResponse
            ]
        );

        if (!$validationResponse) {
            return ['success' => false, 'error_message' => 'No response from AI service (validation).'];
        }

        try {
            $json = $validationResponse;
            $json = str_replace('```json', '', $json);
            $json = str_replace('```', '', $json);

            $response = json_decode($json, true);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error_message' => 'Failed to decode JSON: ' . $e->getMessage(),
            ];
        }

        if (!$response
            || !isset($response['questions'])
            || !is_array($response['questions'])
            || count($response['questions']) === 0) {
            return [
                'success' => false,
                'error_message' => 'Invalid response format from AI service.',
                'response' => $response,
                'raw_response' => $rawResponse,
            ];
        }

        $languages = (new LanguageController())->getAllLanguages();

        $translationController = new TranslationController();

        foreach ($response['questions'] as $item) {
            $question = $item['question'] ?? '';
            $answer = $item['answer'] ?? '';

            if (!$question || !$answer) {
                continue;
            }

            $data = [
                'article_id' => $article->id,
                'question_en' => $question,
                'answer_en' => $answer,
            ];

            foreach ($languages as $locale) {
                if ($locale->language_code === 'en') continue;

                $translations = $translationController->translate([$question, $answer], 'en', $locale->language_code, false);

                $data['question_' . $locale->language_code] = $translations[0] ?? '';
                $data['answer_' . $locale->language_code] = $translations[1] ?? '';
            }

            ArticleFaqEntry::create($data);
        }

        return [
            'success' => true,
            'error_message' => null,
        ];
    }
}
