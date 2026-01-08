<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MailerCheckService
{
    protected string $baseUrl = 'https://app.mailercheck.com/api';
    protected string $token;

    public function __construct()
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $this->token = env('MAILERCHECK_API_TOKEN');
    }

    public function checkSingle(string $email): bool
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        $response = $this->callAPI('POST', '/check/single', [
           'email' => $email
        ]);

        $status = $response['status'] ?? 'invalid';

        return $status === 'valid';
    }

    public function callAPI(string $method, string $endpoint, array $params = []): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $url = $this->baseUrl . $endpoint;

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
