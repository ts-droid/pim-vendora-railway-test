<?php

namespace App\Services;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\TranslationController;
use App\Models\Article;
use App\Models\ArticleFaqEntry;
use App\Services\AI\AIService;
use App\Services\AI\OpenAIService;

class FaqService
{
    const FAQ_MODEL = 'gpt-4o';

    public function generateArticleFAQ(Article $article, int $numberOfQuestions = 3)
    {
        $system = 'Product description:' . PHP_EOL . ($article->shop_description_en ?? '');

        $message = 'You are a e-commerce assistant. Read through the product description and generate exactly ' . $numberOfQuestions . ' FAQ questions and answers.
        The questions should be relevant to the product and the answers should be informative and concise. The questions must be in English.
        Max length of each question is 90 characters and max length of each answer is 200 characters.
        Respond only with a JSON-formatted string with the following structure:
        {
            "questions": [
                {
                    "question": "Question 1",
                    "answer": "Answer 1"
                },
                {
                    "question": "Question 2",
                    "answer": "Answer 2"
                },
                {
                    "question": "Question 3",
                    "answer": "Answer 3"
                }
            ]
        }';

        $AIService = new AIService(self::FAQ_MODEL);
        $rawResponse = $AIService->chatCompletion($system, $message);

        if (!$rawResponse) {
            return;
        }

        try {
            $response = json_decode($rawResponse, true);
        } catch (\Exception $e) {
            return;
        }

        if (!$response
            || !isset($response['questions'])
            || !is_array($response['questions'])
            || count($response['questions']) !== $numberOfQuestions) {
            return;
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
    }
}
