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

    public function test_three_hour_level_fills_the_following_hours(): void
    {
        $levels = $this->levels([
            'time' => ['2026-10-20 00:00', '2026-10-20 03:00', '2026-10-20 21:00'],
            'freezinglevelheight' => [2500, 2800, 3300],
        ]);

        $this->assertSame(2200, $levels['2026-10-20 00']);
        $this->assertSame(2200, $levels['2026-10-20 01']);
        $this->assertSame(2200, $levels['2026-10-20 02']);
        $this->assertSame(2500, $levels['2026-10-20 03']);
        $this->assertSame(2500, $levels['2026-10-20 05']);
        $this->assertNull($levels['2026-10-20 21']);
        $this->assertNull($levels['2026-10-20 23']);
        $this->assertArrayNotHasKey('2026-10-20 06', $levels);
    }

    public function test_three_hour_level_crosses_midnight(): void
    {
        $levels = $this->levels([
            'time' => ['2026-10-20 22:00'],
            'freezinglevelheight' => [2500],
        ]);

        $this->assertSame(2200, $levels['2026-10-20 23']);
        $this->assertSame(2200, $levels['2026-10-21 00']);
    }

    public function test_missing_three_hour_package_stays_empty(): void
    {
        $this->assertSame([], $this->levels([]));
    }

    /** @param  array<string, mixed>  $data3h
     * @return array<string, ?int>
     */
    private function levels(array $data3h): array
    {
        $method = new \ReflectionMethod(MeteoblueForecast::class, 'snowLevelsByHour');
        $method->setAccessible(true);

        return $method->invoke(new MeteoblueForecast(), $data3h);
    }
}
