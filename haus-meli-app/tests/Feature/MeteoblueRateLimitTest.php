<?php

namespace Tests\Feature;

use App\Services\MeteoblueForecast;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeteoblueRateLimitTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_rate_limit_pauses_further_calls_and_keeps_the_last_forecast(): void
    {
        config(['weather.api_key' => 'test-key']);
        Http::fake([
            'https://my.meteoblue.com/*' => Http::sequence()
                ->push($this->payload(9), 200)
                ->push(['error' => true, 'error_message' => 'too many'], 429, ['Retry-After' => '600'])
                ->push($this->payload(3), 200),
        ]);

        $weather = app(MeteoblueForecast::class);

        $this->assertSame(9, $weather->forecast()['current']['temp']);

        Cache::forget($this->cacheKey($weather));

        $limited = $weather->forecast();
        $this->assertTrue($limited['ok']);
        $this->assertSame(9, $limited['current']['temp']);

        $this->assertSame(9, $weather->forecast()['current']['temp']);
        Http::assertSentCount(2);
    }

    public function test_without_a_stored_forecast_the_pause_is_shown_and_not_retried(): void
    {
        $now = Carbon::now('Europe/Vienna');
        Carbon::setTestNow($now);
        config(['weather.api_key' => 'test-key']);
        Http::fake([
            'https://my.meteoblue.com/*' => Http::sequence()
                ->push(['error' => true], 429, ['Retry-After' => '120'])
                ->push($this->payload(11), 200),
        ]);

        $weather = app(MeteoblueForecast::class);

        $blocked = $weather->forecast();
        $this->assertFalse($blocked['ok']);
        $this->assertSame('Wetterdienst begrenzt die Abrufe.', $blocked['error']);

        Carbon::setTestNow($now->copy()->addSeconds(90));
        $this->assertSame('Wetterdienst begrenzt die Abrufe.', $weather->forecast()['error']);
        Http::assertSentCount(1);

        Carbon::setTestNow($now->copy()->addSeconds(130));
        $this->assertSame(11, $weather->forecast()['current']['temp']);
        Http::assertSentCount(2);
    }

    private function cacheKey(MeteoblueForecast $weather): string
    {
        $place = $weather->resolve();

        return sprintf(
            'weather.meteoblue.v9.%s.%s.%s.%d',
            $place['id'],
            $place['lat'],
            $place['lon'],
            $place['asl']
        );
    }

    /** @return array<string, mixed> */
    private function payload(int $temp): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Vienna'));
        $day = $now->format('Y-m-d');
        $hour = $now->format('Y-m-d H:00');

        return [
            'metadata' => ['modelrun_updatetime_utc' => $day.'T06:00:00Z'],
            'data_day' => [
                'time' => [$day],
                'indexto1hvalues_start' => [0],
                'indexto1hvalues_end' => [0],
                'pictocode' => [1],
                'precipitation' => [0],
                'precipitation_probability' => [0],
                'windspeed_mean' => [5],
                'windspeed_max' => [10],
                'temperature_max' => [12],
                'temperature_min' => [4],
                'winddirection' => [180],
                'sunshine_time' => [60],
                'uvindex' => [2],
            ],
            'data_1h' => [
                'time' => [$hour],
                'pictocode' => [1],
                'isdaylight' => [1],
                'precipitation' => [0],
                'temperature' => [$temp],
                'felttemperature' => [$temp],
                'precipitation_probability' => [0],
                'windspeed' => [5],
                'winddirection' => [180],
            ],
            'data_3h' => ['time' => [], 'freezinglevelheight' => []],
        ];
    }
}
