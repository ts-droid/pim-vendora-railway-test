<?php

namespace App\Services\GS1;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * GS1 Sweden / Validoo integration for generating and activating GTIN numbers.
 *
 * Config lives in config/services.php under 'gs1'. Token is obtained from
 * my.gs1.se → API-åtkomst.
 *
 * @see https://developer.gs1.se/api-details#api=numberseries&operation=post-licence-api-licences-key-generate
 * @see https://developer.gs1.se/api-details#api=tradeitem&operation=post-tradeitem-api-activate-gtins
 */
class Gs1ValidooService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $companyPrefix,
        private readonly string $generateUrl,
        private readonly string $activateUrl,
        private readonly string $defaultBrand,
        private readonly string $countryCode,
    ) {
    }

    public static function fromConfig(): self
    {
        $c = config('services.gs1');
        return new self(
            apiKey: $c['api_key'] ?? '',
            companyPrefix: $c['company_prefix'] ?? '',
            generateUrl: $c['generate_url'],
            activateUrl: $c['activate_url'],
            defaultBrand: $c['default_brand'] ?? 'BUNDLE',
            countryCode: $c['country_code'] ?? '752',
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->companyPrefix !== '';
    }

    /**
     * Generate one or more GTIN-13 numbers from the company's number series.
     *
     * @return string[] Array of generated GTIN strings.
     * @throws RuntimeException if not configured or the API call fails.
     */
    public function generateGTIN(int $amount = 1): array
    {
        $this->assertConfigured();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Ocp-Apim-Subscription-Key' => $this->apiKey,
        ])->post($this->generateUrl, [
            'gs1KeyType' => 'GTIN',
            'companyPrefix' => $this->companyPrefix,
            'amountOfNumbers' => $amount,
            'leadingNumber' => 0,
        ]);

        if ($response->status() === 401 || $response->status() === 403) {
            $body = trim(substr($response->body(), 0, 300));
            throw new RuntimeException(
                "GS1 auth failed ({$response->status()}). Check GS1_API_KEY. Validoo says: " . ($body ?: '(empty body)')
            );
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                'GS1 generate error ' . $response->status() . ': ' . $response->body()
            );
        }

        $data = $response->json();

        // Response: { keys: ["7350076480033", ...], code: "1" }
        // code "1" = Created successfully, "5" = Failed
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
     * @param string $gtin        GTIN-13 or GTIN-14 (13-digit input is padded with a leading zero)
     * @param string $productName Product name (sv)
     * @param string|null $brandName Optional brand name, defaults to configured default brand
     * @param string $status     'ACTIVE' | 'DRAFT' | 'INACTIVE'
     * @return string batchId returned by the API
     * @throws RuntimeException
     */
    public function activateGTIN(string $gtin, string $productName, ?string $brandName = null, string $status = 'DRAFT'): string
    {
        $this->assertConfigured();

        // GTIN must be 14 digits for activate API
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

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Ocp-Apim-Subscription-Key' => $this->apiKey,
        ])->post($this->activateUrl, $payload);

        if ($response->status() === 401 || $response->status() === 403) {
            $body = trim(substr($response->body(), 0, 300));
            throw new RuntimeException(
                "GS1 auth failed ({$response->status()}). Check GS1_API_KEY. Validoo says: " . ($body ?: '(empty body)')
            );
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                'GS1 activate error ' . $response->status() . ': ' . $response->body()
            );
        }

        // 202 Accepted → batchId as string
        return trim($response->body(), " \t\n\r\"");
    }

    /**
     * Generate a single GTIN and immediately submit it as DRAFT. Convenience
     * for the auto-GTIN flow on bundle creation.
     *
     * Returns the generated GTIN. If activation fails the GTIN is still
     * returned (it's already allocated — activation can be retried).
     */
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

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException(
                'GS1 not configured. Set GS1_API_KEY and GS1_COMPANY_PREFIX in .env'
            );
        }
    }
}
