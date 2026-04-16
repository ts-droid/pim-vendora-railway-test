<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\SmartRounder;
use Tests\TestCase;

class SmartRounderTest extends TestCase
{
    public function test_zero_or_negative_returns_unchanged(): void
    {
        $this->assertEquals(0, SmartRounder::round('SEK', 0));
        $this->assertEquals(-5, SmartRounder::round('SEK', -5));
    }

    /**
     * @dataProvider sek_cases
     */
    public function test_sek_rounds_up_to_nearest_psychological_ending(float $raw, float $expected): void
    {
        $this->assertEquals($expected, SmartRounder::round('SEK', $raw));
    }

    public static function sek_cases(): array
    {
        return [
            'under 500 rounds up to .29'      => [120.0, 129.0],
            'under 500 picks .49'             => [145.0, 149.0],
            'under 500 picks .99'             => [180.0, 199.0],
            '500-1000 picks .49'              => [520.0, 549.0],
            '500-1000 picks .99'              => [780.0, 799.0],
            '1000-10000 picks .99'            => [2500.0, 2599.0],
            'above 10000 picks .499 or .999'  => [15000.0, 15499.0],
            'NOK follows same rules as SEK'   => [199.0, 199.0],
            'DKK follows same rules as SEK'   => [79.0, 79.0],
        ];
    }

    /**
     * @dataProvider eur_cases
     */
    public function test_eur_rounds_to_decimal_psychological_endings(float $raw, float $expected): void
    {
        $this->assertEquals($expected, SmartRounder::round('EUR', $raw));
    }

    public static function eur_cases(): array
    {
        return [
            'under 50 picks X.99'  => [12.50, 12.99],
            'under 50 picks 9.99'  => [8.00, 9.99],
            'under 100 picks 9.99' => [78.00, 79.99],
            'under 1000 picks 9.99' => [250.00, 259.99],
            'above 1000 picks .99'  => [1200.00, 1249.99],
        ];
    }

    public function test_format_uses_integer_for_nordic_currencies(): void
    {
        $this->assertEquals('129', SmartRounder::format('SEK', 129.4));
        $this->assertEquals('130', SmartRounder::format('NOK', 129.6));
        $this->assertEquals('79', SmartRounder::format('DKK', 79.2));
    }

    public function test_format_uses_two_decimals_for_eur_and_usd(): void
    {
        $this->assertEquals('14.99', SmartRounder::format('EUR', 14.99));
        $this->assertEquals('9.99', SmartRounder::format('USD', 9.99));
    }
}
