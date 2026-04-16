<?php

namespace Tests\Unit\Services\Pricing;

use App\Models\Article;
use App\Services\EcbService;
use App\Services\Pricing\PriceCalculatorService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

/**
 * Unit-tests the calculator math without hitting the database. We build
 * Article instances in memory and pass a mocked EcbService so no network
 * or DB calls occur.
 */
class PriceCalculatorServiceTest extends TestCase
{
    private EcbService&MockObject $ecb;
    private PriceCalculatorService $calc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ecb = $this->createMock(EcbService::class);
        // Default: return SEK price scaled by simple fake rates
        $this->ecb->method('convertCurrency')->willReturnCallback(function ($amount, $from, $to) {
            $rates = ['SEK' => 1.0, 'EUR' => 0.088, 'NOK' => 0.92, 'DKK' => 0.65];
            return $amount * ($rates[$to] / $rates[$from]);
        });

        $this->calc = new PriceCalculatorService($this->ecb);
    }

    private function makeArticle(array $attrs): Article
    {
        $a = new Article();
        foreach ($attrs as $k => $v) {
            $a->{$k} = $v;
        }
        return $a;
    }

    public function test_calculate_with_rrp_derives_final_price_and_our_margin(): void
    {
        $article = $this->makeArticle([
            'article_number' => 'TEST-1',
            'article_type' => 'FinishedGoodItem',
            'cost_price_avg' => 50.0,
            'rek_price_SEK' => 200.0,
            'standard_reseller_margin' => 30.0,
            'minimum_margin' => 20.0,
        ]);

        $result = $this->calc->calculate(
            article: $article,
            source: 'rrp',
            rrpExSEK: 200.0,
            resellerMargin: 30.0,
        );

        // basePriceEx = 200 * 0.70 = 140
        // ourMargin = (140 - 50) / 140 = 64.29%
        $this->assertEquals(140.0, $result['final_price_ex']);
        $this->assertEquals(64.29, $result['our_margin']);
        $this->assertFalse($result['below_min_margin']);
        $this->assertEquals(50.0, $result['cost']);
    }

    public function test_calculate_with_our_margin_derives_rrp(): void
    {
        $article = $this->makeArticle([
            'article_type' => 'FinishedGoodItem',
            'cost_price_avg' => 100.0,
            'standard_reseller_margin' => 30.0,
            'minimum_margin' => 18.0,
        ]);

        $result = $this->calc->calculate(
            article: $article,
            source: 'margin',
            ourMargin: 25.0,
            resellerMargin: 30.0,
        );

        // basePriceEx = 100 / 0.75 = 133.33
        // rrpEx = 133.33 / 0.70 = 190.48
        $this->assertEqualsWithDelta(190.48, $result['rrp_ex_sek'], 0.1);
        $this->assertEqualsWithDelta(25.0, $result['our_margin'], 0.1);
    }

    public function test_flags_below_min_margin_when_our_margin_lower_than_minimum(): void
    {
        $article = $this->makeArticle([
            'article_type' => 'FinishedGoodItem',
            'cost_price_avg' => 100.0,
            'minimum_margin' => 20.0,
            'standard_reseller_margin' => 30.0,
        ]);

        $result = $this->calc->calculate(
            article: $article,
            source: 'rrp',
            rrpExSEK: 170.0,
            resellerMargin: 30.0,
        );

        // basePriceEx = 119, margin = (119-100)/119 = 15.97% < 20%
        $this->assertTrue($result['below_min_margin']);
    }

    public function test_currency_grid_includes_all_four_currencies(): void
    {
        $article = $this->makeArticle([
            'article_type' => 'FinishedGoodItem',
            'cost_price_avg' => 50.0,
            'rek_price_SEK' => 200.0,
            'standard_reseller_margin' => 30.0,
            'minimum_margin' => 18.0,
        ]);

        $result = $this->calc->calculate(
            article: $article,
            source: 'rrp',
            rrpExSEK: 200.0,
            resellerMargin: 30.0,
        );

        $this->assertArrayHasKey('SEK', $result['currencies']);
        $this->assertArrayHasKey('EUR', $result['currencies']);
        $this->assertArrayHasKey('NOK', $result['currencies']);
        $this->assertArrayHasKey('DKK', $result['currencies']);

        $sek = $result['currencies']['SEK'];
        $this->assertArrayHasKey('rrp_inc_raw', $sek);
        $this->assertArrayHasKey('rrp_inc_rounded', $sek);
        $this->assertArrayHasKey('rrp_ex_rounded', $sek);
    }

    public function test_zero_cost_does_not_crash(): void
    {
        $article = $this->makeArticle([
            'article_type' => 'FinishedGoodItem',
            'cost_price_avg' => 0,
            'standard_reseller_margin' => 30.0,
            'minimum_margin' => 18.0,
        ]);

        $result = $this->calc->calculate(
            article: $article,
            source: 'rrp',
            rrpExSEK: 100.0,
            resellerMargin: 30.0,
        );

        $this->assertEquals(0.0, $result['cost']);
        $this->assertEquals(100.0, $result['our_margin']); // all margin when cost=0
    }
}
