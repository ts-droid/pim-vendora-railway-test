<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class ClaudeService implements AIInterface
{
    protected string $model;

    private string $apiKey;
    private string $apiURL;

    public function __construct(string $model)
    {
        $this->model = $model;

        $this->apiKey = env('CLAUDE_KEY', '');
        $this->apiURL = env('CLAUDE_ENDPOINT', '');

        if (!$this->apiKey || !$this->apiURL) {
            throw new \Exception('Claude API key or endpoint not set');
        }
    }

    public function chatCompletion(string $system, string $message, ?float $temperature = null): string
    {
        $response = $this->callAPI('POST', '/v1/messages', $this->getChatCompletionBody($system, $message));

        return $response['content'][0]['text'] ?? '';
    }

    public function streamChatCompletion(string $system, string $message): array
    {
        $requestBody = $this->getChatCompletionBody($system, $message);
        $requestBody['stream'] = true;

        return [
            'type' => 'claude',
            'headers' => $this->getHeaders(),
            'url' => $this->apiURL . '/v1/messages',
            'body' => $requestBody,
        ];
    }

    private function callAPI(string $method, string $endpoint, array $data = []): array
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
            'anthropic-version' => '2023-06-01',
            'x-api-key' => $this->apiKey,
        ];
    }

    private function getChatCompletionBody(string $system, string $message): array
    {
        return [
            'model' => $this->model,
            'max_tokens' => 4000,
            'system' => $system,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ]
        ];
    }
}
