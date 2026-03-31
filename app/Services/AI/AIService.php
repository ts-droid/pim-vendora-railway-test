<?php

namespace App\Services\AI;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;

class AIService
{
    protected AIInterface $aiService;

    public function __construct(string $model = '')
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $model = $model ?: default_ai_model();

        if (str_starts_with($model, 'claude')) {
            $this->aiService = new ClaudeService($model);
        }
        else if (str_starts_with($model, 'pplx') || str_starts_with($model, 'llama')) {
            $this->aiService = new PerplexityService($model);
        }
        else if (str_starts_with($model, 'deepseek')) {
            $this->aiService = new DeepSeekService($model);
        }
        else {
            $this->aiService = new OpenAIService($model);
        }
    }

    public function chatCompletion(string $system, string $message, ?float $temperature = null, $imageURL = ''): string
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        return $this->aiService->chatCompletion($system, $message, $temperature, $imageURL);
    }

    public function streamChatCompletion(string $system, string $message, string $imageURL = ''): array
    {
        $__serviceLogContext = ['service' => static::class, 'method' => __FUNCTION__, 'args' => func_get_args(),];
        action_log('Invoked service method.', $__serviceLogContext);

        return $this->aiService->streamChatCompletion($system, $message, $imageURL);
    }

    public function createMessageBatch(array $items): array
    {
        $__serviceLogContext = ['service' => static::class, 'method' => __FUNCTION__, 'args' => func_get_args(),];
        action_log('Invoked service method.', $__serviceLogContext);

        return $this->aiService->createMessageBatch($items);
    }

    public function translate(string $text, string $fromLocale, string $toLocale): string
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Fetch languages
        $languageController = new LanguageController();
        $fromLanguage = $languageController->getLanguageByCode($fromLocale);
        $toLanguage = $languageController->getLanguageByCode($toLocale);

        // Load translation prompt
        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode('translation_prompt');

        $inputs = [
            'fromLanguage' => ($fromLanguage->title ?? ''),
            'toLanguage' => ($toLanguage->title ?? ''),
            'text' => $text,
        ];

        $system = $promptController->replaceInputs($prompt->system, $inputs);
        $message = $promptController->replaceInputs($prompt->message, $inputs);

        // Generate translation
        return $this->chatCompletion($system, $message);
    }

    public function chatCompletionWithTranslations(string $system, string $message, string $baseLocale): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Generate base translation
        $text = $this->chatCompletion($system, $message);
        $text = trim($text, '"');

        $translations = [
            $baseLocale => $text
        ];

        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $locale) {
            if ($locale == $baseLocale) {
                continue;
            }

            $translations[$locale->language_code] = $this->translate($text, $baseLocale, $locale->language_code);
        }

        return $translations;
    }
}
