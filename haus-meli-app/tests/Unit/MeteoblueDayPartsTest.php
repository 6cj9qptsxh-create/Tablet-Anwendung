<?php

namespace Tests\Unit;

use App\Services\MeteoblueForecast;
use PHPUnit\Framework\TestCase;

class MeteoblueDayPartsTest extends TestCase
{
    /** Ein voller Tag, hell von 06 bis 19 Uhr. $byHour: Stunde => [icon, rain, sky]. */
    private function day(string $icon = 'sunny', array $byHour = []): array
    {
        $rows = [];
        for ($h = 0; $h < 24; $h++) {
            $custom = $byHour[$h] ?? [];
            $rows[] = [
                'time' => sprintf('%02d:00', $h),
                'icon' => $custom[0] ?? $icon,
                'rain' => $custom[1] ?? 0.0,
                'daylight' => $h >= 6 && $h <= 19,
            ];
        }

        return $rows;
    }

    private function call(string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod(MeteoblueForecast::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(new MeteoblueForecast(), ...$args);
    }

    private function halves(array $rows, mixed $sunMinutes): array
    {
        $fallback = ['icon' => 'cloud', 'label' => 'Bedeckt', 'terminal' => 'bewoelkt'];

        return $this->call('dayHalves', $rows, $sunMinutes, $fallback);
    }

    public function test_fog_in_the_morning_and_sun_in_the_afternoon(): void
    {
        $byHour = [];
        for ($h = 6; $h <= 12; $h++) {
            $byHour[$h] = ['foggy'];
        }
        $parts = $this->halves($this->day('sunny', $byHour), 7 * 60);

        $this->assertSame('foggy', $parts['morning']['icon']);
        $this->assertSame('sunny', $parts['afternoon']['icon']);
    }

    public function test_rain_only_in_the_afternoon(): void
    {
        $byHour = [];
        for ($h = 14; $h <= 18; $h++) {
            $byHour[$h] = ['rainy', 0.6];
        }
        $parts = $this->halves($this->day('sunny', $byHour), 9 * 60);

        $this->assertSame('rainy', $parts['afternoon']['icon']);
        $this->assertSame('sunny', $parts['morning']['icon']);
    }

    public function test_one_sun_hour_shows_no_sun_in_either_half(): void
    {
        $parts = $this->halves($this->day('sunny'), 60);

        $this->assertSame('cloud', $parts['morning']['icon']);
        $this->assertSame('cloud', $parts['afternoon']['icon']);
    }

    public function test_sun_stays_in_the_half_where_it_shines(): void
    {
        $byHour = [];
        for ($h = 13; $h <= 19; $h++) {
            $byHour[$h] = ['cloud'];
        }
        $parts = $this->halves($this->day('sunny', $byHour), 7 * 60);

        $this->assertSame('sunny', $parts['morning']['icon']);
        $this->assertSame('cloud', $parts['afternoon']['icon']);
    }

    public function test_mixed_day_shows_sun_with_cloud_in_both_halves(): void
    {
        $parts = $this->halves($this->day('sunny'), 6 * 60);

        $this->assertSame('partly_cloudy_day', $parts['morning']['icon']);
        $this->assertSame('partly_cloudy_day', $parts['afternoon']['icon']);
    }

    public function test_clear_night_is_a_moon(): void
    {
        $night = $this->call('nightLook', $this->day('sunny'), $this->day('sunny'));

        $this->assertSame('bedtime', $night['icon']);
    }

    public function test_night_runs_from_sunset_to_next_sunrise(): void
    {
        $today = $this->day('sunny');
        $tomorrow = $this->day('sunny', [3 => ['rainy', 1.0], 4 => ['rainy', 1.0], 5 => ['rainy', 0.8]]);

        $this->assertSame('rainy', $this->call('nightLook', $today, $tomorrow)['icon']);
    }

    public function test_rain_after_sunrise_does_not_belong_to_the_night(): void
    {
        $today = $this->day('sunny');
        $tomorrow = $this->day('sunny', [7 => ['rainy', 2.0], 8 => ['rainy', 2.0], 9 => ['rainy', 2.0]]);

        $this->assertSame('bedtime', $this->call('nightLook', $today, $tomorrow)['icon']);
    }

    public function test_partly_cloudy_night(): void
    {
        $byHour = [];
        foreach ([20, 21, 22, 23] as $h) {
            $byHour[$h] = ['partly_cloudy_night'];
        }
        $today = $this->day('sunny', $byHour);
        $tomorrow = $this->day('sunny', [0 => ['partly_cloudy_night'], 1 => ['partly_cloudy_night'], 2 => ['partly_cloudy_night'], 3 => ['cloud'], 4 => ['cloud']]);

        $this->assertSame('partly_cloudy_night', $this->call('nightLook', $today, $tomorrow)['icon']);
    }

    public function test_last_day_uses_only_the_evening(): void
    {
        $this->assertSame('bedtime', $this->call('nightLook', $this->day('sunny'), [])['icon']);
    }

    public function test_night_symbol_swaps_only_sun_icons(): void
    {
        $this->assertSame('bedtime', $this->call('nightSymbol', 'sunny'));
        $this->assertSame('partly_cloudy_night', $this->call('nightSymbol', 'partly_cloudy_day'));
        $this->assertSame('rainy', $this->call('nightSymbol', 'rainy'));
    }
}
