<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\TranslationController;
use App\Models\Article;
use App\Models\ArticleFaqEntry;
use App\Models\Prompt;
use App\Services\AI\AIService;
use App\Utilities\MetaDataStorage;
use Illuminate\Database\Eloquent\Collection;

class FaqService
{
    private AiService $aiService;

    private PromptController $promptController;

    private array $batchRequests;

    private int $currentStep;

    private string $batchId;

    private Prompt $generatePrompt;
    private Prompt $validatePrompt;

    public function run(Collection $articles)
    {
        $this->aiService = new AIService('claude-sonnet-4-6'); // TODO: Load model from some config

        $this->currentStep = (int) ConfigController::getConfig('faq_service_current_step');
        $this->batchId = (string) ConfigController::getConfig('faq_service_batch_id');

        $this->promptController = new PromptController();
        $this->generatePrompt = $this->promptController->getBySystemCode('faq_articles_generate');
        $this->validatePrompt = $this->promptController->getBySystemCode('faq_articles_validate');

        switch ($this->currentStep) {
            case 0:
                $this->generate($articles);
                break;

            case 1:
                $this->validate();
                break;

            case 2:
                $this->update();
                break;
        }
    }

    public function generate(Collection $articles): void
    {
        foreach ($articles as $article) {
            $inputs = [
                'product_description' => ($article->shop_description_en ?? '')
            ];

            $system = $this->promptController->replaceInputs($this->generatePrompt->system, $inputs);
            $message = $this->promptController->replaceInputs($this->generatePrompt->message, $inputs);

            $this->batchRequests[] = [
                'system' => $system,
                'message' => $message,
                'meta_data' => [
                    'article_id' => $article->id,
                    'inputs' => $inputs
                ]
            ];

            $article->update(['last_faq_generation' => now()]);
        }

        $response = $this->aiService->createMessageBatch($this->batchRequests);
        $batchId = $response['id'] ?? null;

        if (!$batchId) {
            echo 'Failed to create message batch.' . PHP_EOL;
            return; // Failed to create batch
        }

        ConfigController::setConfigs([
            'faq_service_current_step' => '1',
            'faq_service_batch_id' => $batchId,
        ]);

        echo 'New batch created.' . PHP_EOL;
    }

    public function validate()
    {
        if (!$this->batchId) {
            ConfigController::setConfigs(['faq_service_current_step' => '0']);
            return;
        }

        $status = $this->aiService->getMessageBatch($this->batchId);
        if ($status['processing_status'] !== 'ended') {
            echo 'validate() waiting for batch to complete.' . PHP_EOL;
            return; // Still processing, just wait
        }

        $results = $this->aiService->getBatchTexts($this->batchId);
        foreach ($results as $customID => $text) {
            $metaDataKey = 'aibatch:' . $customID;
            $metaData = MetaDataStorage::get($metaDataKey);

            $inputs = $metaData['inputs'];
            $inputs['faq_json'] = $text;

            $system = $this->promptController->replaceInputs($this->validatePrompt->system, $inputs);
            $message = $this->promptController->replaceInputs($this->validatePrompt->message, $inputs);

            unset($metaData['inputs']);

            $this->batchRequests[] = [
                'system' => $system,
                'message' => $message,
                'meta_data' => $metaData
            ];

            MetaDataStorage::delete($metaDataKey);
        }

        $response = $this->aiService->createMessageBatch($this->batchRequests);
        $batchId = $response['id'] ?? null;

        if (!$batchId) {
            echo 'validate() failed.' . PHP_EOL;
            ConfigController::setConfigs(['faq_service_current_step' => '0']);
            return;
        }

        ConfigController::setConfigs([
            'faq_service_current_step' => '2',
            'faq_service_batch_id' => $batchId,
        ]);

        echo 'validate() completed.' . PHP_EOL;
    }

    public function update()
    {
        if (!$this->batchId) {
            ConfigController::setConfigs(['faq_service_current_step' => '0']);
            return;
        }

        $status = $this->aiService->getMessageBatch($this->batchId);
        if ($status['processing_status'] !== 'ended') {
            echo 'update() waiting for batch to complete.' . PHP_EOL;
            return; // Still processing, just wait
        }

        $results = $this->aiService->getBatchTexts($this->batchId);
        foreach ($results as $customID => $text) {
            $metaDataKey = 'aibatch:' . $customID;
            $metaData = MetaDataStorage::get($metaDataKey);

            try {
                $json = $text;
                $json = str_replace('```json', '', $json);
                $json = str_replace('```', '', $json);
                $response = json_decode($json, true);
            } catch (\Exception $e) {
                continue;
            }

            if (!$response
                || !isset($response['questions'])
                || !is_array($response['questions'])
                || count($response['questions']) === 0) {
                continue;
            }

            foreach ($response['questions'] as $item) {
                $question = $item['question'] ?? '';
                $answer = $item['answer'] ?? '';

                if (!$question || !$answer) {
                    continue;
                }

                ArticleFaqEntry::create([
                    'article_id' => $metaData['article_id'],
                    'question_en' => $question,
                    'answer_en' => $answer,
                ]);
            }

            MetaDataStorage::delete($metaDataKey);
        }

        ConfigController::setConfigs(['faq_service_current_step' => '0']);

        echo 'update() completed.' . PHP_EOL;
    }







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
