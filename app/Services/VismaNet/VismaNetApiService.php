<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;
use App\Models\PurchaseOrder;
use App\Services\ApiLogger;
use Illuminate\Support\Facades\Http;

class VismaNetApiService
{
    protected string $baseUrl;

    protected string $clientId;
    protected string $clientSecret;

    protected string $callbackUrl;

    protected array $appScope;

    protected int $defaultPageSize;

    protected int $callCount = 0;
    protected int $sleepTime = 1;

    public function __construct()
    {
        $this->baseUrl = 'https://integration.visma.net';

        $this->clientId = env('VISMA_CLIENT_ID', '');
        $this->clientSecret = env('VISMA_CLIENT_SECRET', '');

        $this->callbackUrl = route('visma.callback');

        $this->defaultPageSize = 500;

        $this->appScope = [
            'openid',
            'email',
            'profile',
            'tenants',
            'offline_access',
            'vismanet_erp_interactive_api:create',
            'vismanet_erp_interactive_api:delete',
            'vismanet_erp_interactive_api:read',
            'vismanet_erp_interactive_api:update'
        ];

        if (!$this->clientId || !$this->clientSecret) {
            throw new \Exception('Visma.net credentials not set.');
        }
    }

    /**
     * Makes a call to the Visma.net API and returns the response.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param string $accessToken
     * @return array
     */
    public function callAPI(string $method, string $endpoint, array $params = [], string $accessToken = '', bool $rawResponse = false, bool $logRequest = false): array
    {
        if ($this->callCount > 0) {
            sleep($this->sleepTime);
        }

        $accessToken = $accessToken ?: $this->getAccessToken();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        if ($params) {
            $headers['Content-Type'] = 'application/json';
        }

        if (str_starts_with(strtolower($endpoint), 'http')) {
            $url = $endpoint;
        }
        else {
            $url = $this->baseUrl . '/API/controller/api' . $endpoint;
        }

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

            case 'PATCH':
                $response = Http::withHeaders($headers)
                    ->connectTimeout(600)
                    ->timeout(600)
                    ->patch($url, $params);
                break;

            case 'GET':
            default:
                $response = Http::withHeaders($headers)
                    ->connectTimeout(600)
                    ->timeout(600)
                    ->get($url);
                break;
        }

        $this->callCount++;

        $response = [
            'success' => $response->successful(),
            'response' => $rawResponse ? ($response->body() ?? '') : ($response->json() ?? []),
            'http_code' => $response->status(),
            'headers' => $response->headers(),
        ];

        $metaData = [
            'access_token' => $accessToken
        ];

        // Log the API call
        if ($logRequest) {
            ApiLogger::log(
                ApiLogger::TYPE_VISMA,
                $url,
                $params,
                $method,
                $response,
                $metaData
            );
        }

        return $response;
    }

    /**
     * Get id from location uri (aa.com/bb/cc/id --> id)
     *
     * @param string $location
     * @return string
     */
    public function getIdFromLocation(string $location)
    {
        preg_match('/.+\/(.+)$/', $location, $matches);

        if ($matches && $matches[1]) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Returns the saved access token, generates a new on if it has expired.
     *
     * @return string
     */
    private function getAccessToken(): string
    {
        $accessToken = ConfigController::getConfig('vismanet_access_token');
        $refreshToken = ConfigController::getConfig('vismanet_refresh_token');
        $expiresAt = ConfigController::getConfig('vismanet_token_expires_at');

        if ($expiresAt < (time() - 60)) {
            $accessToken = $this->generateAccessToken($refreshToken);
        }

        return $accessToken;
    }

    /**
     * Returns paged results from the API.
     *
     * @param string $endpoint
     * @param array $params
     * @return array
     */
    protected function getPagedResult(string $endpoint, array $params = []): array
    {
        $params['pageSize'] = $this->defaultPageSize;

        if (!isset($params['pageNumber'])) {
            $params['pageNumber'] = 1;
        }

        // Convert boolean values to string
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $params[$key] = $value ? 'true' : 'false';
            }
        }

        $response = $this->callAPI('GET', ($endpoint . '?' . http_build_query($params)));
        $rows = $response['response'];

        if ($rows && count($rows) === $this->defaultPageSize) {
            $params['pageNumber']++;
            $rows = array_merge($rows, $this->getPagedResult($endpoint, $params));
        }

        return $rows;
    }

    /**
     * Generates and returns a new access token.
     *
     * @param string $code
     * @param bool $isAuthCode
     * @return string
     */
    private function generateAccessToken(string $code, bool $isAuthCode = false): string
    {
        if ($isAuthCode) {
            $params = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->callbackUrl,
            ];
        }
        else {
            $params = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $code,
            ];
        }

        $response = Http::asForm()->post('https://connect.visma.com/connect/token', $params)
            ->json();

        $accessToken = $response['access_token'] ?? '';
        $refreshToken = $response['refresh_token'] ?? '';
        $expiresIn = $response['expires_in'] ?? 0;
        $expiresAt = time() + $expiresIn;

        ConfigController::setConfigs([
            'vismanet_access_token' => $accessToken,
            'vismanet_refresh_token' => $refreshToken,
            'vismanet_token_expires_at' => $expiresAt,
        ]);

        return $accessToken;
    }
}
