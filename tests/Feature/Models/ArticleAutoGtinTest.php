<?php

namespace Tests\Feature\Models;

use App\Models\Article;
use App\Services\GS1\Gs1ValidooService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies the Article::saving() hook that auto-generates a GTIN for
 * bundle articles without an EAN.
 */
class ArticleAutoGtinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Stub GS1 endpoints so no real HTTP happens
        Http::fake([
            'services.validoo.se/licence.api/*' => Http::response([
                'keys' => ['7350167970017'],
                'code' => '1',
            ], 200),
            'services.validoo.se/tradeitem.api/*' => Http::response('batch-abc', 202),
        ]);

        // Bind a configured GS1 service so isConfigured() is true
        $this->app->instance(
            Gs1ValidooService::class,
            new Gs1ValidooService(
                apiKey: 'test-key',
                companyPrefix: '735016797',
                generateUrl: 'https://services.validoo.se/licence.api/licences/key/generate',
                activateUrl: 'https://services.validoo.se/tradeitem.api/activate/gtins',
                defaultBrand: 'BUNDLE',
                countryCode: '752',
            )
        );
    }

    public function test_bundle_without_ean_gets_gtin_on_save(): void
    {
        $article = new Article();
        $article->article_number = 'BUN-001';
        $article->description = 'Test Bundle';
        $article->article_type = 'Bundle';
        $article->ean = '';
        $article->save();

        $this->assertEquals('7350167970017', $article->ean);
    }

    public function test_bundle_with_existing_ean_is_not_modified(): void
    {
        $article = new Article();
        $article->article_number = 'BUN-002';
        $article->description = 'Test Bundle';
        $article->article_type = 'Bundle';
        $article->ean = '1234567890123';
        $article->save();

        $this->assertEquals('1234567890123', $article->ean);
    }

    public function test_non_bundle_article_does_not_trigger_generation(): void
    {
        $article = new Article();
        $article->article_number = 'STD-001';
        $article->description = 'Standard article';
        $article->article_type = 'FinishedGoodItem';
        $article->ean = '';
        $article->save();

        $this->assertEquals('', $article->ean);
    }

    public function test_gs1_failure_does_not_abort_save(): void
    {
        Http::fake([
            'services.validoo.se/licence.api/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $article = new Article();
        $article->article_number = 'BUN-003';
        $article->description = 'Test Bundle';
        $article->article_type = 'Bundle';
        $article->ean = '';
        $article->save();

        // Save should succeed even if GS1 throws
        $this->assertDatabaseHas('articles', ['article_number' => 'BUN-003']);
        $this->assertEmpty($article->ean);
    }

    public function test_unconfigured_gs1_service_is_a_noop(): void
    {
        $this->app->instance(
            Gs1ValidooService::class,
            new Gs1ValidooService(
                apiKey: '',
                companyPrefix: '',
                generateUrl: 'https://services.validoo.se/licence.api/licences/key/generate',
                activateUrl: 'https://services.validoo.se/tradeitem.api/activate/gtins',
                defaultBrand: 'BUNDLE',
                countryCode: '752',
            )
        );

        $article = new Article();
        $article->article_number = 'BUN-004';
        $article->description = 'Test Bundle';
        $article->article_type = 'Bundle';
        $article->ean = '';
        $article->save();

        $this->assertEmpty($article->ean);
        $this->assertDatabaseHas('articles', ['article_number' => 'BUN-004']);
    }
}
