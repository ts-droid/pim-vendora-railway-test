<?php

namespace App\Services\MailerSend;

use Illuminate\Support\Facades\Http;

class MailerSendApiService
{
    protected string $apiUrl;
    protected string $apiToken;
    protected string $domainId;

    public function __construct()
    {
        $this->apiUrl = env('MAILERSEND_API_URL');
        $this->apiToken = env('MAILERSEND_API_TOKEN');
        $this->domainId = env('MAILERSEND_DOMAIN_ID');
    }

    public function getPages(string $method, string $endpoint, array $params = []): array
    {
        $data = [];

        $page = 1;
        while (true) {
            $params['page'] = $page;
            $response = $this->callApi($method, $endpoint, $params);

            $data = array_merge($data, $response['data'] ?? []);

            $currentPage = (int) ($response['meta']['current_page'] ?? 0);
            $totalPages = (int) ($response['meta']['to'] ?? 0);

            if ($currentPage >= $totalPages) {
                break;
            }

            $page++;
        }

        return $data;
    }

    public function callAPI(string $method, string $endpoint, array $params = []): array
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
