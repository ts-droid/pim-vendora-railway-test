<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TildeService
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.tilde.api_url');
        $this->apiKey = config('services.tilde.api_key');
    }

    public function translateString(string $string, string $sourceLang, string $targetLang): string
    {
        $response = $this->callAPI('POST', '/translate/text', [
            'srcLang' => $sourceLang,
            'trgLang' => $targetLang,
            'domain' => 'general',
            'text' => [$string],
            'termCollections' => [],
        ]);

        return ($response['translations'][0]['translation'] ?? '');
    }

    public function callAPI(string $method, string $endpoint, array $params = []): ?array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Api-Key' => $this->apiKey,
        ];

        $url = $this->apiUrl . $endpoint;
        if ($method == 'GET') {
            $url .= '?' . http_build_query($params);
        }

        switch ($method) {
            case 'POST':
                $response = Http::withHeaders($headers)
                    ->connectTimeout(10)
                    ->timeout(600)
                    ->post($url, $params);
                break;

            case 'GET':
            default:
                $response = Http::withHeaders($headers)
                    ->connectTimeout(10)
                    ->timeout(600)
                    ->get($url);
                break;
        }

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }
}
