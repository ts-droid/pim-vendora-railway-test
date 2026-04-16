<?php

namespace Tests\Unit\Services\GS1;

use App\Services\GS1\Gs1ValidooService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class Gs1ValidooServiceTest extends TestCase
{
    private function makeService(string $apiKey = 'test-key', string $companyPrefix = '735016797'): Gs1ValidooService
    {
        return new Gs1ValidooService(
            apiKey: $apiKey,
            companyPrefix: $companyPrefix,
            generateUrl: 'https://services.validoo.se/licence.api/licences/key/generate',
            activateUrl: 'https://services.validoo.se/tradeitem.api/activate/gtins',
            defaultBrand: 'BUNDLE',
            countryCode: '752',
        );
    }

    public function test_is_configured_requires_key_and_prefix(): void
    {
        $this->assertTrue($this->makeService()->isConfigured());
        $this->assertFalse($this->makeService('', '735016797')->isConfigured());
        $this->assertFalse($this->makeService('key', '')->isConfigured());
    }

    public function test_generate_returns_keys_on_success(): void
    {
        Http::fake([
            'services.validoo.se/licence.api/*' => Http::response([
                'keys' => ['7350167970017', '7350167970024'],
                'code' => '1',
            ], 200),
        ]);

        $keys = $this->makeService()->generateGTIN(2);

        $this->assertCount(2, $keys);
        $this->assertEquals('7350167970017', $keys[0]);
    }

    public function test_generate_throws_on_code_5_failure(): void
    {
        Http::fake([
            'services.validoo.se/licence.api/*' => Http::response([
                'keys' => [],
                'code' => '5',
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->makeService()->generateGTIN(1);
    }

    public function test_generate_throws_on_401_unauthorized(): void
    {
        Http::fake([
            'services.validoo.se/licence.api/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/GS1 auth failed \(401\)/');
        $this->makeService()->generateGTIN(1);
    }

    public function test_activate_pads_13_digit_gtin_to_14(): void
    {
        $capturedBody = null;
        Http::fake([
            'services.validoo.se/tradeitem.api/*' => function ($request) use (&$capturedBody) {
                $capturedBody = $request->data();
                return Http::response('batch-abc-123', 202);
            },
        ]);

        $batchId = $this->makeService()->activateGTIN('7350167970017', 'My Bundle', null, 'DRAFT');

        $this->assertEquals('batch-abc-123', $batchId);
        $this->assertEquals('07350167970017', $capturedBody[0]['gtin']);
        $this->assertEquals('DRAFT', $capturedBody[0]['gtinStatus']);
        $this->assertEquals('BUNDLE', $capturedBody[0]['brandName'][0]['value']);
        $this->assertEquals('sv', $capturedBody[0]['productName'][0]['language']);
    }

    public function test_activate_preserves_14_digit_gtin(): void
    {
        $capturedBody = null;
        Http::fake([
            'services.validoo.se/tradeitem.api/*' => function ($request) use (&$capturedBody) {
                $capturedBody = $request->data();
                return Http::response('batch-xyz', 202);
            },
        ]);

        $this->makeService()->activateGTIN('07350167970017', 'Bundle', 'MyBrand', 'ACTIVE');

        $this->assertEquals('07350167970017', $capturedBody[0]['gtin']);
        $this->assertEquals('MyBrand', $capturedBody[0]['brandName'][0]['value']);
    }

    public function test_generate_and_activate_returns_gtin_even_if_activation_fails(): void
    {
        Http::fake([
            'services.validoo.se/licence.api/*' => Http::response([
                'keys' => ['7350167970017'],
                'code' => '1',
            ], 200),
            'services.validoo.se/tradeitem.api/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $gtin = $this->makeService()->generateAndActivate('My Bundle');

        $this->assertEquals('7350167970017', $gtin);
    }

    public function test_unconfigured_service_throws_with_helpful_message(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/GS1_API_KEY.*GS1_COMPANY_PREFIX/');
        $this->makeService('', '')->generateGTIN();
    }
}
