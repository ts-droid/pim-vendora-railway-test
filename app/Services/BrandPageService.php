<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrandPageService
{
    const API_KEY = 'AJ2Cy3EkizwOX4Xn0Seqwvur1Mv8Ldo0cbik0N26Ma';

    public function callAPI(string $method, string $endpoint, array $params = []): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $getParams = [
            'api_key' => self::API_KEY
        ];

        if ($method == 'GET') {
            $getParams = array_merge($getParams, $params);
        }

        if (str_starts_with($endpoint, 'https://')) {
            $url = $endpoint . '?' . http_build_query($getParams);
        } else {
            $url = 'http://brand-pages.vendora.se/api' . $endpoint . '?' . http_build_query($getParams);
        }

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
