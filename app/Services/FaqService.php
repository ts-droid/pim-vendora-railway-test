<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleFaqEntry;
use App\Services\AI\AIService;
use App\Services\AI\OpenAIService;

class FaqService
{
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

        $AIService = new AIService('gpt-4o');
        $rawResponse = $AIService->chatCompletion($system, $message);

        if (!$rawResponse) {
            return;
        }

        try {
            $response = json_decode($rawResponse, true);
        } catch (\Exception $e) {
            return;
        }

        dd($response);

        if (!$response
            || !isset($response['questions'])
            || !is_array($response['questions'])
            || count($response['questions']) !== $numberOfQuestions) {
            return;
        }

        for ($i = 0;$i < $numberOfQuestions;$i++) {
            $question = $response['questions'][$i]['question'] ?? '';
            $answer = $response['questions'][$i]['answer'] ?? '';

            if (!$question || !$answer) {
                continue;
            }

            ArticleFaqEntry::create([
                'article_id' => $article->id,
                'question' => $question,
                'answer' => $answer,
            ]);
        }
    }
}
