<?php

namespace App\Services\Allianz;

use App\Http\Controllers\ConfigController;
use Illuminate\Support\Facades\Http;

class AllianzApiService
{
    public function makeRequest(string $method, string $endpoint, array $params = [])
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->getToken(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $url = $this->getEndpoint($endpoint);

        switch (strtoupper($method)) {
            case 'POST':
                $response = Http::withHeaders($headers)
                    ->connectTimeout(600)
                    ->timeout(600)
                    ->post($url, $params);
                break;

            case 'PUT':
                $response = Http::withHeaders($headers)
                    ->connectTimeout(600)
                    ->timeout(600)
                    ->put($url, $params);
                break;

            case 'DELETE':
                $response = Http::withHeaders($headers)
                    ->connectTimeout(600)
                    ->timeout(600)
                    ->delete($url, $params);
                break;

            case 'GET':
            default:
                $response = Http::withHeaders($headers)
                    ->connectTimeout(600)
                    ->timeout(600)
                    ->get($url . '?' . http_build_query($params));
                break;
        }

        return $response->json() ?: [];
    }

    private function getToken()
    {
        $token = ConfigController::getConfig('allianz_token');
        $expires = ConfigController::getConfig('allianz_token_expires');

        // Use stored token if it exists and hasn't expired
        if ($token && time() < ($expires - 60)) {
            return $token;
        }

        // Get new token
        $response = Http::post($this->getEndpoint('oauth_token'), [
            'apiKey' => config('allianz.api_key'),
        ]);

        $json = $response->json();

        $token = $json['access_token'] ?? '';
        $expires = time() + ($json['expires_in'] ?? 0);

        // Store the token
        ConfigController::setConfigs([
            'allianz_token' => $token,
            'allianz_token_expires' => $expires,
        ]);

        return $token;
    }

    protected function getEndpoint(string $key)
    {
        return config('allianz.endpoints.' . $key . '.' . (config('allianz.production') ? 'prod' : 'test'));
    }
}
