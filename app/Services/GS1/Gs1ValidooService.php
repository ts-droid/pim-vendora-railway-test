<?php

namespace App\Services\GS1;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * GS1 Sweden / Validoo integration.
 *
 * Auth: OAuth2 password grant against
 * https://validoopwe-apimanagement.azure-api.net/connect/token.
 * The access_token is cached in Laravel's Cache driver for ~55 minutes
 * (Validoo issues them for 60m), with a refresh_token used to extend
 * until it also expires (~120m). When both are expired we fall back
 * to a fresh password grant.
 *
 * Credentials live on MyGS1 → Technical Integration. Each user has
 * clientId/clientSecret + api-username/password.
 *
 * @see https://validoopwe-apimanagement.azure-api.net/ (API portal)
 */
class Gs1ValidooService
{
    private const CACHE_KEY = 'gs1_validoo_token';
    private const TOKEN_LEEWAY_SECONDS = 60; // refresh this long before expiry

    public function __construct(
        private readonly string $tokenUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $username,
        private readonly string $password,
        private readonly string $scope,
        private readonly string $companyPrefix,
        private readonly string $generateUrl,
        private readonly string $activateUrl,
        private readonly string $defaultBrand,
        private readonly string $countryCode,
        private readonly string $environment = 'Production',
    ) {
    }

    public static function fromConfig(): self
    {
        $c = config('services.gs1');
        return new self(
            tokenUrl: self::readSetting('token_url', 'token_url', $c) ?: 'https://validoopwe-apimanagement.azure-api.net/connect/token',
            clientId: self::readSetting('client_id', 'client_id', $c),
            clientSecret: self::readSetting('client_secret', 'client_secret', $c),
            username: self::readSetting('username', 'username', $c),
            password: self::readSetting('password', 'password', $c),
            scope: self::readSetting('scope', 'scope', $c) ?: 'numberseries tradeitem offline_access',
            companyPrefix: self::readSetting('company_prefix', 'company_prefix', $c),
            generateUrl: $c['generate_url'],
            activateUrl: $c['activate_url'],
            defaultBrand: $c['default_brand'] ?? 'BUNDLE',
            countryCode: $c['country_code'] ?? '752',
            environment: self::readSetting('environment', 'environment', $c) ?: 'Production',
        );
    }

    /**
     * Read a GS1 credential: configs-tabellen (UI-skrivna) först,
     * sen config/services.php-värdet (ENV-backed) som fallback.
     * Secrets i configs-tabellen är Crypt-krypterade.
     */
    private static function readSetting(string $dbKey, string $configKey, array $c): string
    {
        try {
            $row = \App\Models\Config::where('config', 'gs1_' . $dbKey)->first();
            if ($row && $row->content !== '') {
                if (in_array($dbKey, ['client_secret', 'password'], true)) {
                    return \Illuminate\Support\Facades\Crypt::decryptString($row->content);
                }
                return (string) $row->content;
            }
        } catch (\Throwable $e) {
            // DB kan saknas under migrationer / Crypt-fel → env-fallback
        }
        return (string) ($c[$configKey] ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== ''
            && $this->clientSecret !== ''
            && $this->username !== ''
            && $this->password !== ''
            && $this->companyPrefix !== '';
    }

    /**
     * Generate one or more GTIN numbers from the company's number series.
     *
     * @return string[] Array of generated GTIN strings.
     * @throws RuntimeException if not configured or the API call fails.
     */
    public function generateGTIN(int $amount = 1): array
    {
        $this->assertConfigured();

        $response = $this->authedRequest()->post($this->generateUrl, [
            'gs1KeyType' => 'GTIN',
            'companyPrefix' => $this->companyPrefix,
            'amountOfNumbers' => $amount,
            'leadingNumber' => 0,
        ]);

        $this->assertAuthOk($response, 'generate');
        if (!$response->successful()) {
            throw new RuntimeException(
                'GS1 generate error ' . $response->status() . ': ' . $response->body()
            );
        }

        $data = $response->json();
        if (($data['code'] ?? null) === '5' || ($data['code'] ?? null) === 5) {
            throw new RuntimeException('GS1 generation failed: ' . json_encode($data));
        }
        if (empty($data['keys']) || !is_array($data['keys'])) {
            throw new RuntimeException('GS1 returned no keys');
        }

        return $data['keys'];
    }

    /**
     * Register a GTIN in GS1's Global Registry Platform.
     *
     * @return string batchId returned by the API
     * @throws RuntimeException
     */
    public function activateGTIN(string $gtin, string $productName, ?string $brandName = null, string $status = 'DRAFT'): string
    {
        $this->assertConfigured();

        $gtin14 = strlen($gtin) === 13 ? '0' . $gtin : $gtin;

        $payload = [[
            'gtin' => $gtin14,
            'gtinStatus' => $status,
            'productName' => [['language' => 'sv', 'value' => $productName ?: 'Bundle']],
            'brandName' => [['language' => 'sv', 'value' => $brandName ?: $this->defaultBrand]],
            'countryOfSaleCode' => [$this->countryCode],
            'isTradeItemAConsumerUnit' => true,
            'isTradeItemABaseUnit' => true,
        ]];

        $response = $this->authedRequest()->post($this->activateUrl, $payload);

        $this->assertAuthOk($response, 'activate');
        if (!$response->successful()) {
            throw new RuntimeException(
                'GS1 activate error ' . $response->status() . ': ' . $response->body()
            );
        }

        return trim($response->body(), " \t\n\r\"");
    }

    public function generateAndActivate(string $productName, ?string $brandName = null): string
    {
        $keys = $this->generateGTIN(1);
        $gtin = $keys[0];

        try {
            $this->activateGTIN($gtin, $productName, $brandName, 'DRAFT');
        } catch (\Throwable $e) {
            Log::warning('GS1 GTIN generated but activation failed', [
                'gtin' => $gtin,
                'error' => $e->getMessage(),
            ]);
        }

        return $gtin;
    }

    public function companyPrefix(): string
    {
        return $this->companyPrefix;
    }

    /**
     * Force a fresh token exchange — useful when the cached token has
     * been invalidated server-side. Clears any cached entry first.
     */
    public function refreshTokenNow(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->getAccessToken();
    }

    private function authedRequest(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->getAccessToken());
    }

    private function assertAuthOk(\Illuminate\Http\Client\Response $response, string $op): void
    {
        if ($response->status() !== 401 && $response->status() !== 403) {
            return;
        }
        // Drop the cached token so the next call re-auths cleanly.
        Cache::forget(self::CACHE_KEY);
        $body = trim(substr($response->body(), 0, 300));
        throw new RuntimeException(
            "GS1 {$op} auth failed ({$response->status()}). Validoo says: " . ($body ?: '(empty body)')
        );
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException(
                'GS1 not configured. Set GS1_CLIENT_ID, GS1_CLIENT_SECRET, GS1_USERNAME, GS1_PASSWORD, GS1_COMPANY_PREFIX.'
            );
        }
    }

    /**
     * Return a valid access token. Tries cache → refresh → password grant.
     */
    private function getAccessToken(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)
            && !empty($cached['access_token'])
            && ($cached['expires_at'] ?? 0) > time() + self::TOKEN_LEEWAY_SECONDS) {
            return $cached['access_token'];
        }

        // Cached access is gone/expired — try refresh if we have one.
        if (is_array($cached) && !empty($cached['refresh_token'])) {
            try {
                $tokens = $this->requestRefresh($cached['refresh_token']);
                $this->cacheTokens($tokens);
                return $tokens['access_token'];
            } catch (\Throwable $e) {
                Log::info('GS1 refresh token failed; falling back to password grant', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $tokens = $this->requestPasswordGrant();
        $this->cacheTokens($tokens);
        return $tokens['access_token'];
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int}
     */
    private function requestPasswordGrant(): array
    {
        // Validoo returnerade 500 "Internal server error" när vi skickade
        // ett 'environment'-fält i bodyn — antar att credentials räcker
        // och att "Production" är en egenskap hos själva nyckeln snarare
        // än ett request-fält. UI-fältet 'environment' lagras för
        // informationsändamål men skickas inte med OAuth-requesten.
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => $this->scope,
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'GS1 token exchange failed (' . $response->status() . '): '
                . substr($response->body(), 0, 300)
            );
        }

        $data = $response->json();
        if (empty($data['access_token'])) {
            throw new RuntimeException('GS1 token response missing access_token: ' . json_encode($data));
        }

        return [
            'access_token' => (string) $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    private function requestRefresh(string $refreshToken): array
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'GS1 refresh failed (' . $response->status() . '): '
                . substr($response->body(), 0, 300)
            );
        }

        $data = $response->json();
        if (empty($data['access_token'])) {
            throw new RuntimeException('GS1 refresh response missing access_token');
        }

        return [
            'access_token' => (string) $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    private function cacheTokens(array $tokens): void
    {
        $expiresIn = (int) $tokens['expires_in'];
        Cache::put(
            self::CACHE_KEY,
            [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at' => time() + $expiresIn,
            ],
            // Keep in cache a bit past expiry so refresh_token remains
            // available for the refresh code path after access expires.
            now()->addSeconds(max($expiresIn, 7200))
        );
    }
}
