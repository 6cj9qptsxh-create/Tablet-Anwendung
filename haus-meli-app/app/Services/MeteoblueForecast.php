<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MeteoblueForecast
{
    /** Volle Balkenhöhe, für jeden Tag und jede Stunde dieselbe Menge. Anzeige in L/m². */
    private const RAIN_FULL_MM = 10.0;
    public function forecast(): array
    {
        $key = (string) config('weather.api_key');
        if ($key === '') {
            return ['ok' => false, 'error' => 'Kein Wetterschlüssel hinterlegt.'];
        }

        $cacheKey = sprintf(
            'weather.meteoblue.v3.%s.%s',
            config('weather.lat'),
            config('weather.lon')
        );
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $this->keepUntilNextHour($cacheKey, $cached);

            return $this->present($cached);
        }

        try {
            $fresh = Cache::lock($cacheKey.'.lock', 25)->block(20, function () use ($cacheKey, $key) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }

                $fresh = $this->download($key);
                Cache::put($cacheKey, $fresh, $this->freshUntil());

                return $fresh;
            });
        } catch (LockTimeoutException $e) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $this->present($cached);
            }

            return ['ok' => false, 'error' => 'Wetterdienst nicht erreichbar.'];
        } catch (Throwable $e) {
            Log::warning('meteoblue request failed', ['message' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Wetterdienst nicht erreichbar.'];
        }

        return $this->present($fresh);
    }

    private function keepUntilNextHour(string $cacheKey, array $cached): void
    {
        $until = $this->freshUntil();
        $left = $until->getTimestamp() - time();
        if ($left < 20) {
            return;
        }

        Cache::put($cacheKey, $cached, $until);
    }

    private function freshUntil(): \DateTimeImmutable
    {
        $tz = new \DateTimeZone((string) config('weather.timezone'));
        $now = new \DateTimeImmutable('now', $tz);
        $until = $now->setTime((int) $now->format('H'), 0, 0)->modify('+1 hour');
        if ($until->getTimestamp() - $now->getTimestamp() < 20) {
            $until = $until->modify('+1 hour');
        }

        return $until;
    }

    public function header(): array
    {
        $data = $this->forecast();
        if (empty($data['ok'])) {
            return $data;
        }

        $now = $data['current'] ?? null;

        return [
            'ok' => true,
            'place' => $data['place'],
            'temp' => $now['temp'] ?? null,
            'icon' => $now['icon'] ?? 'partly_cloudy_day',
            'label' => $now['label'] ?? '',
        ];
    }

    public function terminal(): array
    {
        $data = $this->forecast();
        if (empty($data['ok'])) {
            return $data;
        }

        $tz = new \DateTimeZone((string) config('weather.timezone'));
        $now = new \DateTimeImmutable('now', $tz);
        $today = $now->format('Y-m-d');
        $week = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        $dayLines = [];
        foreach ($data['days'] as $day) {
            $date = (string) ($day['date'] ?? '');
            if ($date < $today) {
                continue;
            }
            $heights = [];
            foreach ($day['rain_slots'] ?? [] as $slot) {
                $heights[] = (int) ($slot['height'] ?? 0);
            }
            $heights = array_pad(array_slice($heights, 0, 8), 8, 0);
            $label = $date === $today
                ? 'Heute'
                : $week[(int) (new \DateTimeImmutable($date, $tz))->format('w')];
            $dayLines[] = implode('  ', [
                $label,
                $day['terminal'],
                $day['max'],
                $day['min'],
                (int) ($day['pop'] ?? 0),
                $this->terminalRain((float) ($day['rain'] ?? 0)),
                $day['sun_text'] ?? '-',
                $day['wind_text'] ?? '-',
                implode(',', $heights),
            ]);
            if (count($dayLines) >= 5) {
                break;
            }
        }

        $hourLines = [];
        $nowKey = $now->format('Y-m-d H');
        foreach ($data['days'] as $day) {
            $date = (string) ($day['date'] ?? '');
            foreach ($day['hours'] ?? [] as $hour) {
                $stamp = $date.' '.substr((string) ($hour['time'] ?? ''), 0, 2);
                if ($stamp < $nowKey) {
                    continue;
                }
                $rain = number_format((float) $hour['rain'], 1, '.', '');
                $hourLines[] = substr((string) $hour['time'], 0, 2).'  '.$hour['terminal'].'  '.$hour['temp'].'  '.$rain;
                if (count($hourLines) >= 16) {
                    break 2;
                }
            }
        }

        return [
            'ok' => true,
            'place' => $data['place'],
            'days_count' => count($dayLines),
            'hours_count' => count($hourLines),
            'days_text' => implode("\n", $dayLines),
            'hours_text' => implode("\n", $hourLines),
        ];
    }

    private function download(string $key): array
    {
        $response = Http::timeout(20)->acceptJson()->get('https://my.meteoblue.com/packages/basic-1h_basic-day_clouds-day', [
            'lat' => config('weather.lat'),
            'lon' => config('weather.lon'),
            'asl' => config('weather.asl'),
            'format' => 'json',
            'tz' => config('weather.timezone'),
            'temperature' => 'C',
            'windspeed' => 'kmh',
            'precipitationamount' => 'mm',
            'forecast_days' => 7,
            'apikey' => $key,
        ]);

        if (! $response->ok() || $response->json('error')) {
            Log::warning('meteoblue response rejected', ['status' => $response->status()]);
            throw new \RuntimeException('meteoblue status '.$response->status());
        }

        return $this->normalize($response->json());
    }

    private function normalize(array $raw): array
    {
        $hours = $raw['data_1h'] ?? [];
        $days = $raw['data_day'] ?? [];
        $meta = $raw['metadata'] ?? [];
        $tz = new \DateTimeZone((string) config('weather.timezone'));
        $now = new \DateTimeImmutable('now', $tz);
        $nowKey = $now->format('Y-m-d H');
        $week = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

        $names = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $outDays = [];
        $count = count($days['time'] ?? []);
        for ($i = 0; $i < $count; $i++) {
            $date = (string) $days['time'][$i];
            $start = (int) ($days['indexto1hvalues_start'][$i] ?? 0);
            $end = (int) ($days['indexto1hvalues_end'][$i] ?? $start);
            $dayCode = (int) ($days['pictocode'][$i] ?? 1);
            $look = $this->look($dayCode);
            $rain = round((float) ($days['precipitation'][$i] ?? 0), 1);
            $pop = (int) round((float) ($days['precipitation_probability'][$i] ?? 0));
            $meanWind = (float) ($days['windspeed_mean'][$i] ?? $days['windspeed_max'][$i] ?? 0);

            $hourRows = [];
            for ($h = $start; $h <= $end; $h++) {
                $stamp = (string) ($hours['time'][$h] ?? '');
                $code = (int) ($hours['pictocode'][$h] ?? $dayCode);
                $hourLook = $this->look($code);
                $hourRows[] = [
                    'time' => substr($stamp, 11, 5),
                    'temp' => (int) round((float) ($hours['temperature'][$h] ?? 0)),
                    'feel' => (int) round((float) ($hours['felttemperature'][$h] ?? 0)),
                    'rain' => round((float) ($hours['precipitation'][$h] ?? 0), 1),
                    'pop' => (int) round((float) ($hours['precipitation_probability'][$h] ?? 0)),
                    'wind' => (int) round((float) ($hours['windspeed'][$h] ?? 0)),
                    'wind_dir' => $this->compass((float) ($hours['winddirection'][$h] ?? 0)),
                    'icon' => $hourLook['icon'],
                    'label' => $hourLook['label'],
                    'terminal' => $hourLook['terminal'],
                    'is_now' => substr($stamp, 0, 13) === $nowKey,
                ];
            }

            $when = new \DateTimeImmutable($date, $tz);
            $outDays[] = [
                'date' => $date,
                'name' => $i === 0 ? 'Heute' : ($i === 1 ? 'Morgen' : $names[(int) $when->format('w')]),
                'short' => $i === 0 ? 'Heute' : $week[(int) $when->format('w')],
                'title' => $when->format('j.n.'),
                'max' => (int) round((float) ($days['temperature_max'][$i] ?? 0)),
                'min' => (int) round((float) ($days['temperature_min'][$i] ?? 0)),
                'rain' => $rain,
                'rain_text' => $this->rainText($rain),
                'pop' => $pop,
                'wind' => (int) round((float) ($days['windspeed_max'][$i] ?? 0)),
                'wind_text' => $this->compass((float) ($days['winddirection'][$i] ?? 0)).' '.$this->beaufort($meanWind),
                'sun_text' => $this->sunText($days['sunshine_time'][$i] ?? null),
                'uv' => (int) round((float) ($days['uvindex'][$i] ?? 0)),
                'icon' => $look['icon'],
                'label' => $look['label'],
                'terminal' => $look['terminal'],
                'hours' => $hourRows,
            ];
        }

        $current = null;
        foreach ($outDays[0]['hours'] ?? [] as $hour) {
            if (! empty($hour['is_now'])) {
                $current = $hour;
                break;
            }
        }
        if ($current === null && ! empty($outDays[0]['hours'])) {
            $current = $outDays[0]['hours'][0];
        }

        return [
            'ok' => true,
            'place' => (string) config('weather.place'),
            'updated' => (string) ($meta['modelrun_updatetime_utc'] ?? ''),
            'current' => $current,
            'days' => $outDays,
        ];
    }

    private function present(array $data): array
    {
        if (empty($data['ok'])) {
            return $data;
        }

        $tz = new \DateTimeZone((string) config('weather.timezone'));
        $now = new \DateTimeImmutable('now', $tz);
        $today = $now->format('Y-m-d');
        $nowKey = $now->format('Y-m-d H');
        $names = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $week = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

        $days = [];
        $current = null;
        foreach ($data['days'] as $day) {
            $date = (string) ($day['date'] ?? '');
            if ($date !== '' && $date < $today) {
                continue;
            }
            $when = new \DateTimeImmutable($date !== '' ? $date : 'now', $tz);
            $index = count($days);
            $day['name'] = $index === 0 ? 'Heute' : ($index === 1 ? 'Morgen' : $names[(int) $when->format('w')]);
            $day['short'] = $index === 0 ? 'Heute' : $week[(int) $when->format('w')];
            $day['rain_text'] = $this->rainText((float) ($day['rain'] ?? 0));

            $hours = $day['hours'] ?? [];
            foreach ($hours as $h => $hour) {
                $mm = (float) ($hour['rain'] ?? 0);
                $hours[$h]['rain_height'] = $this->rainHeight($mm);
                $hours[$h]['rain_text'] = $this->rainText($mm);
                $stamp = $date.' '.substr((string) ($hour['time'] ?? ''), 0, 2);
                $hours[$h]['is_now'] = $stamp === $nowKey;
                if ($hours[$h]['is_now']) {
                    $current = $hours[$h];
                }
            }
            $day['hours'] = $hours;
            $day['rain_slots'] = $this->rainSlots($hours);
            $days[] = $day;
        }

        if ($current === null) {
            foreach ($days[0]['hours'] ?? [] as $hour) {
                $stamp = ((string) ($days[0]['date'] ?? '')).' '.substr((string) ($hour['time'] ?? ''), 0, 2);
                if ($stamp >= $nowKey) {
                    $current = $hour;
                    break;
                }
            }
        }
        if ($current === null && ! empty($days[0]['hours'])) {
            $current = $days[0]['hours'][array_key_last($days[0]['hours'])];
        }

        $last = count($days) - 1;
        foreach ($days as $i => $day) {
            $days[$i]['night'] = $i < $last
                ? $this->nightIcon($day['hours'] ?? [], $days[$i + 1]['hours'] ?? [])
                : null;
        }

        $data['days'] = $days;
        $data['current'] = $current;

        return $data;
    }

    private function nightIcon(array $hours, array $nextHours): ?array
    {
        $pick = null;
        foreach (['00:00', '01:00', '02:00', '03:00'] as $time) {
            foreach ($nextHours as $hour) {
                if (substr((string) ($hour['time'] ?? ''), 0, 5) === $time) {
                    $pick = $hour;
                    break 2;
                }
            }
        }
        if ($pick === null) {
            foreach (array_reverse($hours) as $hour) {
                $clock = (int) substr((string) ($hour['time'] ?? ''), 0, 2);
                if ($clock >= 21) {
                    $pick = $hour;
                    break;
                }
            }
        }
        if ($pick === null) {
            return null;
        }

        $icon = (string) ($pick['icon'] ?? 'cloud');
        if ($icon === 'sunny') {
            $icon = 'bedtime';
        } elseif ($icon === 'partly_cloudy_day') {
            $icon = 'partly_cloudy_night';
        }

        return [
            'icon' => $icon,
            'label' => 'Nacht, '.($pick['label'] ?? ''),
        ];
    }

    private function rainSlots(array $hours): array
    {
        $blocks = array_fill(0, 8, 0.0);
        foreach ($hours as $hour) {
            $clock = (int) substr((string) ($hour['time'] ?? ''), 0, 2);
            $blocks[min(7, intdiv($clock, 3))] += (float) ($hour['rain'] ?? 0);
        }
        $labels = ['0–3 Uhr', '3–6 Uhr', '6–9 Uhr', '9–12 Uhr', '12–15 Uhr', '15–18 Uhr', '18–21 Uhr', '21–24 Uhr'];
        $slots = [];
        foreach ($blocks as $i => $mm) {
            $mm = round($mm, 1);
            $slots[] = [
                'label' => $labels[$i],
                'mm' => $mm,
                'text' => $this->rainText($mm),
                'height' => $this->rainHeight($mm),
            ];
        }

        return $slots;
    }

    private function terminalRain(float $mm): string
    {
        if ($mm <= 0) {
            return '-';
        }
        if (abs($mm - round($mm)) < 0.05) {
            return ((int) round($mm)).'mm';
        }

        return number_format($mm, 1, ',', '').'mm';
    }

    private function rainHeight(float $mm): int
    {
        if ($mm <= 0) {
            return 0;
        }

        return (int) min(100, max(1, round($mm / self::RAIN_FULL_MM * 100)));
    }

    private function rainText(float $mm): string
    {
        if ($mm <= 0) {
            return '–';
        }
        if (abs($mm - round($mm)) < 0.05) {
            return ((int) round($mm)).' L/m²';
        }

        return number_format($mm, 1, ',', '').' L/m²';
    }

    private function sunText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $hours = (int) round(((float) $value) / 60);
        if ($hours <= 0) {
            return null;
        }

        return $hours.'h';
    }

    private function beaufort(float $kmh): int
    {
        foreach ([1, 6, 12, 20, 29, 39, 50, 62, 75, 89, 103, 118] as $step => $limit) {
            if ($kmh < $limit) {
                return $step;
            }
        }

        return 12;
    }

    private function look(int $code): array
    {
        if (in_array($code, [27, 28, 30], true)) {
            return ['icon' => 'thunderstorm', 'label' => 'Gewitter', 'terminal' => 'gewitter'];
        }
        if (in_array($code, [24, 26, 29, 32, 34, 35], true)) {
            return ['icon' => 'weather_snowy', 'label' => 'Schnee', 'terminal' => 'schnee'];
        }
        if (in_array($code, [23, 25, 31, 33], true)) {
            return ['icon' => 'rainy', 'label' => 'Regen', 'terminal' => 'regen'];
        }
        if (in_array($code, [16, 17, 18], true)) {
            return ['icon' => 'foggy', 'label' => 'Nebel', 'terminal' => 'nebel'];
        }
        if (in_array($code, [19, 20, 21, 22], true)) {
            return ['icon' => 'cloud', 'label' => 'Bedeckt', 'terminal' => 'bewoelkt'];
        }
        if (in_array($code, [7, 8, 9, 10, 11, 12], true)) {
            return ['icon' => 'partly_cloudy_day', 'label' => 'Wechselnd', 'terminal' => 'wolkig'];
        }

        return ['icon' => 'sunny', 'label' => 'Klar', 'terminal' => 'sonne'];
    }

    private function compass(float $degrees): string
    {
        $names = ['N', 'NO', 'O', 'SO', 'S', 'SW', 'W', 'NW'];
        $index = ((int) round($degrees / 45)) % 8;

        return $names[$index];
    }
}
