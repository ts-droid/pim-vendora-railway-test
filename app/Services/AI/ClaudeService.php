<?php

namespace App\Services\AI;

use App\Utilities\MetaDataStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ClaudeService implements AIInterface
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

        $this->apiKey = config('services.claude.key', '');
        $this->apiURL = config('services.claude.endpoint', '');

        if (!$this->apiKey || !$this->apiURL) {
            throw new \Exception('Claude API key or endpoint not set');
        }
    }

    public function chatCompletion(string $system, string $message, ?float $temperature = null): string
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->callAPI('POST', '/v1/messages', $this->getChatCompletionBody($system, $message));

        return $response['content'][0]['text'] ?? '';
    }

    public function streamChatCompletion(string $system, string $message): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $requestBody = $this->getChatCompletionBody($system, $message);
        $requestBody['stream'] = true;

        return [
            'type' => 'claude',
            'headers' => $this->getHeaders(),
            'url' => $this->apiURL . '/v1/messages',
            'body' => $requestBody,
        ];
    }

    public function createMessageBatch(array $items): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $requests = [];

        foreach ($items as $item) {
            $customID = $item['custom_id'] ?? Str::random(16);
            $metaData = $item['meta_data'] ?? [];

            MetaDataStorage::set('aibatch:' . $customID, $metaData);

            $requests[] = [
                'custom_id' => $customID,
                'params' => $this->getChatCompletionBody(
                    $item['system'] ?? '',
                    $item['message'] ?? '',
                    $item['temperature'] ?? null,
                    $item['max_token'] ?? 4000
                )
            ];
        }

        return $this->callAPI('POST', '/v1/messages/batches', [
            'requests' => $requests,
        ]);
    }

    public function getMessageBatch(string $batchId): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        return $this->callAPI('GET', '/v1/messages/batches/' . $batchId);
    }

    public function getMessageBatchResults(string $batchId): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $batch = $this->getMessageBatch($batchId);

        if (($batch['processing_status'] ?? null) !== 'ended') {
            return [
                'status' => $batch['processing_status'] ?? 'unknown',
                'results' => [],
            ];
        }

        $resultsUrl = $batch['results_url'] ?? null;

        if (!$resultsUrl) {
            return [
                'status' => 'ended',
                'results' => [],
            ];
        }

        $response = Http::withHeaders($this->getHeaders())
            ->connectTimeout(10)
            ->timeout(600)
            ->get($resultsUrl);

        if (!$response->successful()) {
            return [
                'status' => 'ended',
                'results' => [],
            ];
        }

        return [
            'status' => 'ended',
            'results' => $this->parseJsonlResults($response->body()),
        ];
    }

    public function getBatchTexts(string $batchId): array
    {
        $payload = $this->getMessageBatchResults($batchId);

        $mapped = [];

        foreach ($payload['results'] as $result) {
            $customId = $result['custom_id'] ?? null;
            $type = data_get($result, 'result.type');

            if (!$customId) {
                continue;
            }

            if ($type === 'succeeded') {
                $mapped[$customId] = data_get($result, 'result.message.content.0.text');
            } else {
                $mapped[$customId] = null;
            }
        }

        return $mapped;
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

    private function getChatCompletionBody(string $system, string $message, ?float $temperature = null, int $maxTokens = 4000): array
    {
        $body = [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ]
        ];

        if ($temperature !== null) {
            $body['temperature'] = $temperature;
        }

        return $body;
    }

    private function parseJsonlResults(string $jsonl): array
    {
        $results = [];
        $lines = preg_split("/\r\n|\n|\r/", trim($jsonl));

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $results[] = $decoded;
            }
        }

        return $results;
    }
}
