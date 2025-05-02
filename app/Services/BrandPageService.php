<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrandPageService
{
    public function callAPI(string $method, string $endpoint, array $params = []): array
    {
        $getParams = [];

        if ($method == 'GET') {
            $getParams = array_merge($getParams, $params);
        }

        $url = 'http://brand-pages.vendora.se/api' . $endpoint . '?' . http_build_query($getParams);

        switch (strtoupper($method)) {
            case 'POST':
                $response = Http::connectTimeout(300)
                    ->timeout(300)
                    ->post($url, $params);
                break;

            case 'GET':
            default:
                $response = Http::connectTimeout(300)
                    ->timeout(300)
                    ->get($url);
                break;
        }

        if (!$response->successful()) {
            return [
                'success' => false,
                'data' => [],
                'error_message' => 'Error calling API (HTTP: ' . $response->status() . ')'
            ];
        }

        return $response->json();
    }
}
