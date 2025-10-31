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

        $this->apiKey = config('services.openai.key', '');
        $this->apiURL = config('services.openai.endpoint', '');

        if (!$this->apiKey || !$this->apiURL) {
            throw new \Exception('OpenAI API key or endpoint not set');
        }
    }

    public function generateImage(string $prompt, string $imageBase64): array
    {
        return $this->callAPI('POST', '/responses', [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $prompt],
                        ['type' => 'input_image', 'image_url' => $imageBase64]
                    ]
                ]
            ],
            'tools' => [['type' => 'image_generation']]
        ]);
    }

    public function getEmbedding(string $text): array
    {
        $response = $this->callAPI('POST', '/embeddings', [
            'input' => $text,
            'model' => $this->model
        ]);

        return $response['data'][0]['embedding'] ?? [];
    }

    public function chatCompletion(string $system, string $message, ?float $temperature = null, ?string $imageURL = ''): string
    {
        $response = $this->callAPI('POST', '/chat/completions', $this->getChatCompletionBody($system, $message, $temperature, $imageURL));

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

    public function streamChatCompletion(string $system, string $message, string $imageURL = ''): array
    {
        $requestBody = $this->getChatCompletionBody($system, $message, null, $imageURL);
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

        if (request()->get('dump') == '1') {
            dd($response->json());
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

    private function getChatCompletionBody(string $system, string $message, ?float $temperature = null, ?string $imageURL = ''): array
    {
        $userContent = [];

        $userContent[] = [
            'type' => 'text',
            'text' => $message,
        ];

        if (!empty($imageURL)) {
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $imageURL
                ]
            ];
        }

        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system,
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ]
            ],
        ];

        if (str_contains($this->model, 'gpt-5')) {
            $body['reasoning_effort'] = 'low';
        }

        if ($temperature !== null) {
            $body['temperature'] = $temperature;
        }

        return $body;
    }
}
