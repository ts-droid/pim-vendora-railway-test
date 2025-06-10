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
    const FAQ_MODEL = 'gpt-4o';

    public function generateArticleFAQ(Article $article, int $numberOfQuestions = 3): array
    {
        $promptController = new PromptController();

        $prompt = $promptController->getBySystemCode('article_faq');

        $rawResponse = $promptController->execute(
            $prompt->id,
            [
                'product_description' => ($article->shop_description_en ?? ''),
                'number_of_questions' => $numberOfQuestions,
            ],
            '',
            self::FAQ_MODEL
        );

        if (!$rawResponse) {
            return [
                'success' => false,
                'error_message' => 'No response from AI service.',
            ];
        }

        try {
            $response = json_decode($rawResponse, true);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error_message' => 'Failed to decode JSON: ' . $e->getMessage(),
            ];
        }

        if (!$response
            || !isset($response['questions'])
            || !is_array($response['questions'])
            || count($response['questions']) !== $numberOfQuestions) {
            return [
                'success' => false,
                'error_message' => 'Invalid response format from AI service.',
                'response' => $response,
                'raw_response' => $rawResponse,
            ];
        }

        $languages = (new LanguageController())->getAllLanguages();

        $translationController = new TranslationController();

        for ($i = 0;$i < $numberOfQuestions;$i++) {
            $question = $response['questions'][$i]['question'] ?? '';
            $answer = $response['questions'][$i]['answer'] ?? '';

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
