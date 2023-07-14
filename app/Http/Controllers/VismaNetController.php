<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VismaNetController extends Controller
{
    const API_URL = 'https://integration.visma.net';

    const SLEEP_TIME = 1;
    const PAGE_SIZE = 500;

    const APP_SCOPE = [
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

    // Number of calls made to the API
    private int $callCount = 0;

    // Visma.net app credentials
    private string $clientID = '';
    private string $clientSecret = '';

    // Callback URL
    private string $callbackURL = '';

    /**
     * @throws \Exception
     */
    function __construct()
    {
        $this->clientID = env('VISMA_CLIENT_ID', '');
        $this->clientSecret = env('VISMA_CLIENT_SECRET', '');

        $this->callbackURL = route('visma.callback');

        if (!$this->clientID || !$this->clientSecret) {
            throw new \Exception('Visma.net API credentials not set.');
        }
    }

    /**
     * Fetches all data from Visma.net.
     *
     * @return void
     */
    public function fetchAll(): void
    {
        $this->fetchCustomers();
    }

    /**
     * Fetches customers from Visma.net updated after the given date.
     * If no date is given, the last updated date is fetched from the database.
     *
     * @param string $updatedAfter
     * @return void
     */
    public function fetchCustomers(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_customer_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = $updatedAfter;
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $customers = $this->getPagedResult('/v1/customers', $params);

        if ($customers) {
            $customerController = new CustomerController();

            foreach ($customers as $customer) {
                $customerData = [
                    'external_id' => $customer['internalId'] ?? null,
                    'customer_number' => $customer['number'] ?? null,
                    'vat_number' => $customer['vatRegistrationId'] ?? null,
                    'org_number' => $customer['corporateId'] ?? null,
                    'name' => $customer['name'] ?? null,
                ];

                // Require vat number to fetch
                if (!$customerData['vat_number']) {
                    continue;
                }

                $existingCustomers = $customerController->get(new Request([
                    'vat_number' => $customerData['vat_number']
                ]));

                if (!$existingCustomers) {
                    // Create new customer
                    $customerController->store(new Request($customerData));
                }
                else {
                    // Update existing customer
                    $customerController->update(new Request($customerData), $existingCustomers[0]);
                }
            }
        }

        ConfigController::setConfigs(['vismanet_last_customer_fetch' => $fetchTime]);
    }

    /**
     * Handles the oauth2 callback.
     *
     * @param Request $request
     * @return bool
     */
    public function authCallback(Request $request): bool
    {
        $authCode = $request->code ?? null;
        if (!$authCode) {
            return false;
        }

        $this->generateAccessToken($authCode, true);

        return true;
    }

    /**
     * Returns the URL to redirect the user to for authentication.
     *
     * @return string
     */
    public function getAuthURL()
    {
        return 'https://connect.visma.com/connect/authorize?' . http_build_query([
                'client_id' => $this->clientID,
                'scope' => implode(' ', self::APP_SCOPE),
                'response_type' => 'code',
                'response_mode' => 'form_post',
                'redirect_uri' => $this->callbackURL,
            ]);
    }

    /**
     * Returns true if the app is authenticated.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        $data = $this->getPagedResult('/v1/organization');

        if (!$data) {
            return false;
        }

        return (count($data) > 0);
    }

    /**
     * Returns paged result from the API.
     *
     * @param string $endpoint
     * @param array $params
     * @return array
     */
    private function getPagedResult(string $endpoint, array $params = []): array
    {
        $params['pageSize'] = self::PAGE_SIZE;

        if (!isset($params['pageNumber'])) {
            $params['pageNumber'] = 1;
        }

        // Convert boolean values to string
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $params[$key] = $value ? 'true' : 'false';
            }
        }

        $rows = $this->callAPI('GET', ($endpoint . '?' . http_build_query($params)));

        if ($rows && count($rows) === self::PAGE_SIZE) {
            $params['pageNumber']++;
            $rows = array_merge($rows, $this->getPagedResult($endpoint, $params));
        }

        return $rows;
    }

    /**
     * Makes a call to the API and returns the result.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param string $accessToken
     * @return array|mixed
     */
    private function callAPI(string $method, string $endpoint, array $params = [], string $accessToken = '')
    {
        if ($this->callCount > 0) {
            sleep(self::SLEEP_TIME);
        }

        $headers = [
            'Authorization' => 'Bearer ' . ($accessToken ?: $this->getAccessToken()),
        ];

        if ($params) {
            $headers['Content-Type'] = 'application/json';
        }

        if (substr($endpoint, 0, '4') === 'http') {
            $url = $endpoint;
        }
        else {
            $url = self::API_URL . '/API/controller/api' . $endpoint;
        }

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
                    ->get($url);
                break;
        }

        $this->callCount++;

        return $response->json() ?: [];
    }

    /**
     * Returns the saved access token, generates new one if expired.
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
     * Generates and returns an access token.
     *
     * @param string $code
     * @param bool $isAuthCode
     * @return string
     */
    private function generateAccessToken(string $code, bool $isAuthCode = false): string
    {
        if ($isAuthCode) {
            $params = [
                'client_id' => $this->clientID,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->callbackURL,
            ];
        }
        else {
            $params = [
                'client_id' => $this->clientID,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $code
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
