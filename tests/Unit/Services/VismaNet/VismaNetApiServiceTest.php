<?php

namespace Tests\Unit\Services\VismaNet;

use App\Services\VismaNet\VismaNetApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VismaNetApiServiceTest extends TestCase
{
    public function test_successful_api_call_returns_expected_response()
    {
        Http::fake([
            'https://integration.visma.net/API/controller/api*' => Http::response([
                'data' => 'test data',
            ], 200),
        ]);

        $service = new VismaNetApiService();

        $response = $service->callAPI('GET', '/test');

        $this->assertTrue($response['success']);
        $this->assertEquals('test data', $response['response']['data']);
    }

    public function test_unsuccessful_api_call_returns_expected_response()
    {
        Http::fake([
            'https://integration.visma.net/API/controller/api*' => Http::response(null, 500),
        ]);

        $service = new VismaNetApiService();

        $response = $service->callAPI('GET', '/test');

        $this->assertFalse($response['success']);
    }

    public function test_get_id_from_location_returns_expected_id()
    {
        $service = new VismaNetApiService();

        $id = $service->getIdFromLocation('http://example.com/test/123');

        $this->assertEquals('123', $id);
    }
}
