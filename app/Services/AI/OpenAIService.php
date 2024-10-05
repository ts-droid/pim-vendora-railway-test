<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class OpenAIService implements AIInterface
{
    protected string $model;

    private string $apiKey;
    private string $apiURL;

    public function __construct(string $model)
    {
        $this->model = $model;

        $this->apiKey = env('OPEN_AI_KEY', '');
        $this->apiURL = env('OPEN_AI_ENDPOINT', '');

        if (!$this->apiKey || !$this->apiURL) {
            throw new \Exception('OpenAI API key or endpoint not set');
        }
    }

    public function chatCompletion(string $system, string $message): string
    {
        $response = $this->callAPI('POST', '/chat/completions', $this->getChatCompletionBody($system, $message));

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

    public function streamChatCompletion(string $system, string $message): array
    {
        $requestBody = $this->getChatCompletionBody($system, $message);
        $requestBody['stream'] = true;

        return [
            'type' => 'openai',
            'headers' => $this->getHeaders(),
            'url' => $this->apiURL . '/chat/completions',
            'body' => $requestBody,
        ];
    }

    public function callAPI(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->apiURL . $endpoint;

        $request = Http::withHeaders($this->getHeaders())
            ->connectTimeout(10)
            ->timeout(600);

        switch (strtoupper($method)) {
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

    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];
    }

    private function getChatCompletionBody(string $system, string $message): array
    {
        return [
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
    }
}
