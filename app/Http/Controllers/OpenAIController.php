<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OpenAIController extends Controller
{
    private string $model;

    private string $apiKey;
    private string $apiURL;

    public function __construct(string $model = '')
    {
        $this->model = $model ?: env('OPEN_AI_DEFAULT_MODEL', 'gpt-4-1106-preview');

        if (in_array($this->model, ['pplx-7b-online', 'pplx-70b-online'])) {
            $this->apiKey = env('PPLX_KEY', '');
            $this->apiURL = env('PPLX_ENDPOINT', '');
        }
        else {
            $this->apiKey = env('OPEN_AI_KEY', '');
            $this->apiURL = env('OPEN_AI_ENDPOINT', '');
        }
    }

    public function translate(string $text, string $fromLocale, string $toLocale): string
    {
        $languageController = new LanguageController();
        $fromLanguage = $languageController->getLanguageByCode($fromLocale);
        $toLanguage = $languageController->getLanguageByCode($toLocale);

        return $this->chatCompletion(
            'You will be provided with a sentence in ' . ($fromLanguage->title ?? '') . ', and your task is to translate it into ' . ($toLanguage->title ?? '') . '.',
            $text,
        );
    }

    public function chatCompletionWithTranslations(string $system, string $message, string $baseLocale): array
    {
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

    public function chatCompletion(string $system, string $message): string
    {
        $request = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system,
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ]
            ],
        ];

        $response = $this->callAPI('POST', '/chat/completions', $request);

        $chatResponse = '';

        if (isset($response['choices']) && is_array($response['choices'])) {
            foreach ($response['choices'] as $message) {
                if (($message['message']['role'] ?? '') == 'assistant') {
                    $chatResponse .= ($message['message']['content'] ?? '');
                }
            }
        }

        return $chatResponse;
    }


    /**
     * Makes a call to the OpenAI API
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return array
     */
    private function callAPI(string $method, string $endpoint, array $data = []): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        $url = $this->apiURL . $endpoint;

        $request = Http::withHeaders($headers)
            ->connectTimeout(10)
            ->timeout(600);

        switch ($method) {
            case 'POST':
                $response = $request->post($url, $data);
                break;

            case 'GET':
            default:
                $response = $request->get($url . '?' . http_build_query($data));
                break;
        }

        if (!$response->successful()) {
            return [];
        }

        return $response->json();
    }
}
