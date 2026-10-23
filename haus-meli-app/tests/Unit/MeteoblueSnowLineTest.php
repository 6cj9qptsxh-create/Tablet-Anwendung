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

    public function test_day_snow_line_weights_hours_by_precipitation(): void
    {
        $snow = $this->dayLine([
            ['rain' => 5, 'freeze_m' => 2500],
            ['rain' => 1, 'freeze_m' => 3100],
            ['rain' => 0, 'freeze_m' => 1500],
        ]);

        $this->assertSame(2300, $snow['snow_m']);
        $this->assertTrue($snow['weighted']);
    }

    public function test_heavier_precipitation_pulls_the_day_snow_line(): void
    {
        $snow = $this->dayLine([
            ['rain' => 10, 'freeze_m' => 2000],
            ['rain' => 1, 'freeze_m' => 4000],
        ]);

        $this->assertSame(1900, $snow['snow_m']);
        $this->assertTrue($snow['weighted']);
    }

    public function test_precipitation_above_the_limit_hides_the_day_snow_line(): void
    {
        $snow = $this->dayLine([
            ['rain' => 8, 'freeze_m' => 4200],
        ]);

        $this->assertNull($snow['snow_m']);
        $this->assertTrue($snow['weighted']);
    }

    public function test_precipitation_below_the_point_is_shown_in_millimetres(): void
    {
        $this->assertTrue($this->call('fallsAsSnow', 1600, 1900));
        $this->assertFalse($this->call('fallsAsSnow', 2400, 1900));
        $this->assertFalse($this->call('fallsAsSnow', 1900, 1900));
        $this->assertFalse($this->call('fallsAsSnow', null, 1900));
        $this->assertSame('4 mm', $this->call('rainText', 4.0, true));
        $this->assertSame('1,5 mm', $this->call('rainText', 1.5, true));
        $this->assertSame('4 L/m²', $this->call('rainText', 4.0, false));
        $this->assertSame('–', $this->call('rainText', 0.0, true));
    }

    public function test_dry_day_hides_the_snow_line(): void
    {
        $snow = $this->dayLine([
            ['rain' => 0, 'freeze_m' => 1800],
            ['rain' => 2, 'freeze_m' => null],
        ]);

        $this->assertNull($snow['snow_m']);
        $this->assertFalse($snow['weighted']);
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

    /**
     * @param  list<array{rain: float|int, freeze_m: mixed}>  $hours
     * @return array{snow_m: ?int, weighted: bool}
     */
    private function dayLine(array $hours): array
    {
        return $this->call('snowLineForDay', $hours);
    }

    private function call(string $name, mixed ...$args): mixed
    {
        $method = new \ReflectionMethod(MeteoblueForecast::class, $name);
        $method->setAccessible(true);

        return $method->invoke(new MeteoblueForecast(), ...$args);
    }
}
