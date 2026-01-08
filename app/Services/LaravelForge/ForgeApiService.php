<?php

namespace App\Services\LaravelForge;

use Illuminate\Support\Facades\Http;

class ForgeApiService
{
    protected string $apiUrl;
    protected string $apiToken;

    public function __construct()
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $this->apiUrl = env('FORGE_API_URL');
        $this->apiToken = env('FORGE_API_TOKEN');
    }

    protected function callAPI(string $method, string $endpoint, array $params = [])
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $url = $this->apiUrl . $endpoint;

        switch (strtoupper($method)) {
            case 'POST':
                $response = HTTP::withHeaders($headers)
                    ->connectTimeout(600)
                    ->timeout(600)
                    ->post($url, $params);
                break;

            case 'PUT':
                $response = HTTP::withHeaders($headers)
                    ->connectTimeout(600)
                    ->timeout(600)
                    ->put($url, $params);
                break;

            case 'DELETE':
                $response = HTTP::withHeaders($headers)
                    ->connectTimeout(600)
                    ->timeout(600)
                    ->delete($url, $params);
                break;

            case 'GET':
            default:
                $response = HTTP::withHeaders($headers)
                    ->connectTimeout(600)
                    ->timeout(600)
                    ->get($url . '?' . http_build_query($params));
                break;
        }

        return $response->json() ?: [];
    }
}
