<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OpenAIService implements AIInterface
{
    protected string $model;

    private string $apiKey;
    private string $apiURL;

    public function __construct(string $model)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $this->model = $model;

        $this->apiKey = config('services.openai.key', '');
        $this->apiURL = config('services.openai.endpoint', '');

        if (!$this->apiKey || !$this->apiURL) {
            throw new \Exception('OpenAI API key or endpoint not set');
        }
    }

    public function generateImage(string $prompt, string $imageBase64): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

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

    public function generateImageV2(string $prompt, string $imageBase64, string $mimeType, string $model): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        if (str_contains($imageBase64, ',')) {
            [, $imageBase64] = explode(',', $imageBase64, 2);
        }

        $imageBinary = base64_decode($imageBase64);

        $attachments = [
            [
                'name' => 'image',
                'contents' => $imageBinary,
                'headers' => ['Content-Type' => $mimeType]
            ]
        ];

        $body = [
            'model' => $model,
            'prompt' => $prompt,
        ];

        return $this->callAPI('POST', '/images/edits', $body, $attachments);
    }

    public function getEmbedding(string $text): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->callAPI('POST', '/embeddings', [
            'input' => $text,
            'model' => $this->model
        ]);

        return $response['data'][0]['embedding'] ?? [];
    }

    public function chatCompletion(string $system, string $message, ?float $temperature = null, ?string $imageURL = ''): string
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->callAPI('POST', '/chat/completions', $this->getChatCompletionBody($system, $message, $temperature, $imageURL));

        $chatResponse = '';

        if (isset($response['choices']) && is_array($response['choices'])) {
            foreach ($response['choices'] as $message) {
                if (($message['message']['role'] ?? '') == 'assistant') {
                    $chatResponse .= ($message['message']['content'] ?? '');
                }
            }
        }

        // Log the usage
        if (isset($response['usage'])) {
            $this->logUsage(
                $this->model,
                ($response['usage']['prompt_tokens'] ?? 0),
                ($response['usage']['completion_tokens'] ?? 0),
            );
        }

        return $chatResponse;
    }

    public function streamChatCompletion(string $system, string $message, string $imageURL = ''): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $requestBody = $this->getChatCompletionBody($system, $message, null, $imageURL);
        $requestBody['stream'] = true;

        return [
            'type' => 'openai',
            'headers' => $this->getHeaders(),
            'url' => $this->apiURL . '/chat/completions',
            'body' => $requestBody,
        ];
    }

    public function callAPI(string $method, string $endpoint, array $data = [], array $attachments = []): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $url = $this->apiURL . $endpoint;

        $hasAttachments = !empty($attachments);

        $request = Http::withHeaders($this->getHeaders(!$hasAttachments))
            ->connectTimeout(10)
            ->timeout(600);

        if ($hasAttachments) {
            $request = $request->asMultipart();
            foreach ($attachments as $attachment) {
                $request = $request->attach(
                    $attachment['name'],
                    $attachment['contents'],
                    $attachment['filename'] ?? 'file',
                    $attachment['headers'] ?? [],
                );
            }
        }

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

    private function getHeaders($json = true): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey
        ];

        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
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

    private function logUsage(string $model, int $promptTokens, int $completionTokens): void
    {
        DB::table('openai_usage')->updateOrInsert(
            ['date' => date('Y-m-d')],
            [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
