<?php

namespace Tests\Unit;

use App\Services\MeteoblueForecast;
use PHPUnit\Framework\TestCase;

class MeteoblueSnowLineTest extends TestCase
{
    private function line(mixed $freezingLevel): ?int
    {
        $method = new \ReflectionMethod(MeteoblueForecast::class, 'snowLine');
        $method->setAccessible(true);

        return $method->invoke(new MeteoblueForecast(), $freezingLevel);
    }

    public function test_snow_line_is_three_hundred_meters_below_freezing_level(): void
    {
        $this->assertSame(2200, $this->line(2500));
    }

    public function test_snow_line_rounds_to_one_hundred_meters(): void
    {
        $this->assertSame(2400, $this->line(2734));
        $this->assertSame(2200, $this->line(2450));
    }

    public function test_snow_line_at_or_above_3000_meters_is_hidden(): void
    {
        $this->assertSame(2900, $this->line(3240));
        $this->assertNull($this->line(3290));
        $this->assertNull($this->line(3300));
        $this->assertNull($this->line(4200));
    }

    public function test_snow_line_below_sea_level_is_zero(): void
    {
        $this->assertSame(0, $this->line(200));
        $this->assertSame(100, $this->line(400));
    }

    public function test_missing_freezing_level_stays_hidden(): void
    {
        $this->assertNull($this->line(null));
        $this->assertNull($this->line(''));
    }
}
