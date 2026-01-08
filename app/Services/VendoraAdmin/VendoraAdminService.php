<?php

namespace App\Services\VendoraAdmin;

use Illuminate\Support\Facades\Http;

class VendoraAdminService
{
    protected string $webUrl;

    protected string $apiUrl;

    protected string $apiKey;

    public function __construct()
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $this->webUrl = env('VENDORA_ADMIN_WEB_URL', '');
        $this->apiUrl = env('VENDORA_ADMIN_API_URL', '');
        $this->apiKey = env('VENDORA_ADMIN_API_KEY', '');

        if (!$this->webUrl || !$this->apiUrl || !$this->apiKey) {
            throw new \Exception('Missing credentials for Vendora Admin.');
        }
    }

    /**
     * Makes a call to the API.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @return array|mixed
     */
    public function callAPI(string $method, string $endpoint, array $params = [])
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $method = strtoupper($method);

        $getParams = [
            'api_token' => $this->apiKey,
        ];

        if ($method === 'GET') {
            $getParams = array_merge($getParams, $params);
        }

        $url = $this->apiUrl . $endpoint . '?' . http_build_query($getParams);

        switch ($method)
        {
            case 'POST':
                $response = Http::asForm()->post($url, $params);
                break;

            case 'GET':
            default:
                $response = Http::get($url);
                break;
        }

        return $response->json() ?? [];
    }
}
