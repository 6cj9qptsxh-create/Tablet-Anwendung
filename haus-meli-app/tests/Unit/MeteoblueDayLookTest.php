<?php

namespace Tests\Unit;

use App\Services\MeteoblueForecast;
use PHPUnit\Framework\TestCase;

class MeteoblueDayLookTest extends TestCase
{
    private function rows(array $icons, array $rain = [], bool $flagged = true): array
    {
        $rows = [];
        foreach ($icons as $i => $icon) {
            $hour = 7 + $i;
            $rows[] = [
                'time' => sprintf('%02d:00', $hour),
                'icon' => $icon,
                'rain' => $rain[$i] ?? 0.0,
                'daylight' => $flagged ? true : null,
            ];
        }

        return $rows;
    }

    private function icon(array $rows, float $rain, mixed $sunMinutes, int $dayCode = 1): string
    {
        $method = new \ReflectionMethod(MeteoblueForecast::class, 'dayLook');
        $method->setAccessible(true);

        return $method->invoke(new MeteoblueForecast(), $rows, $rain, $sunMinutes, $dayCode)['icon'];
    }

    public function test_one_sun_hour_is_not_sunny(): void
    {
        $rows = $this->rows(array_fill(0, 13, 'sunny'));

        $this->assertSame('cloud', $this->icon($rows, 0.0, 60));
    }

    public function test_full_sun_stays_sunny(): void
    {
        $rows = $this->rows(array_fill(0, 13, 'sunny'));

        $this->assertSame('sunny', $this->icon($rows, 0.0, 11 * 60));
    }

    public function test_half_sun_is_changeable(): void
    {
        $rows = $this->rows(array_fill(0, 13, 'sunny'));

        $this->assertSame('partly_cloudy_day', $this->icon($rows, 0.0, 6 * 60));
    }

    public function test_a_few_sun_hours_give_sun_with_cloud(): void
    {
        $rows = $this->rows(array_fill(0, 13, 'sunny'));

        $this->assertSame('partly_cloudy_day', $this->icon($rows, 0.0, 4 * 60));
    }

    public function test_rain_beats_sunshine(): void
    {
        $rain = [0, 0, 0.5, 0.6, 0.4, 0, 0, 0, 0, 0, 0, 0, 0];
        $rows = $this->rows(array_fill(0, 13, 'rainy'), $rain);

        $this->assertSame('rainy', $this->icon($rows, 1.5, 5 * 60));
    }

    public function test_a_drizzle_does_not_make_a_rainy_day(): void
    {
        $rows = $this->rows(array_fill(0, 13, 'sunny'), [0, 0, 0.2]);

        $this->assertSame('sunny', $this->icon($rows, 0.2, 10 * 60));
    }

    public function test_thunder_wins_over_rain(): void
    {
        $icons = array_fill(0, 13, 'rainy');
        $icons[5] = 'thunderstorm';
        $rain = array_fill(0, 13, 0.5);

        $this->assertSame('thunderstorm', $this->icon($this->rows($icons, $rain), 6.0, 60));
    }

    public function test_snow_day(): void
    {
        $rain = array_fill(0, 13, 0.4);
        $rows = $this->rows(array_fill(0, 13, 'weather_snowy'), $rain);

        $this->assertSame('weather_snowy', $this->icon($rows, 5.0, 0));
    }

    public function test_fog_day(): void
    {
        $rows = $this->rows(array_fill(0, 13, 'foggy'));

        $this->assertSame('foggy', $this->icon($rows, 0.0, 30));
    }

    public function test_missing_sunshine_falls_back_to_hourly_icons(): void
    {
        $icons = array_merge(array_fill(0, 1, 'sunny'), array_fill(0, 12, 'cloud'));

        $this->assertSame('cloud', $this->icon($this->rows($icons), 0.0, null));
    }

    public function test_without_daylight_flag_uses_clock_window(): void
    {
        $rows = $this->rows(array_fill(0, 13, 'sunny'), [], false);

        $this->assertSame('cloud', $this->icon($rows, 0.0, 60));
    }

    public function test_no_hours_falls_back_to_day_code(): void
    {
        $this->assertSame('cloud', $this->icon([], 0.0, null, 19));
    }
}
