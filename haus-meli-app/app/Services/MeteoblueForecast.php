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

    /** Schneefallgrenze nur zeigen, solange sie unter dieser Höhe liegt. */
    private const SNOW_LINE_LIMIT_M = 3000;

    /**
     * meteoblue liefert die Nullgradgrenze. Die Schneefallgrenze liegt etwa
     * 300 m darunter, weil die Flocken erst auf dem Weg nach unten schmelzen.
     */
    private const SNOW_LINE_BELOW_FREEZING_M = 300;

    /** 1 mm Wasser entspricht etwa 1 cm Neuschnee. */
    private const SNOW_CM_PER_MM = 1.0;

    /** @return array<string, array{label:string,zones:array<string,array{lat:float,lon:float,asl:int}>}> */
    public function orts(): array
    {
        $orts = config('weather.orts');

        return is_array($orts) ? $orts : [];
    }

    /**
     * @return array{id:string,ort:string,zone:string,label:string,lat:float,lon:float,asl:int,zones:list<string>}
     */
    public function resolve(?string $ort = null, ?string $zone = null): array
    {
        $orts = $this->orts();
        $ort = is_string($ort) && isset($orts[$ort]) ? $ort : 'lauterach';
        if (! isset($orts[$ort])) {
            $ort = (string) array_key_first($orts);
        }
        $row = $orts[$ort];
        $zones = is_array($row['zones'] ?? null) ? $row['zones'] : [];
        // Frühere Reiter Berg: See heißt Medrig, Kappl heißt Dias, Ischgl heißt Idalp.
        if ($zone === 'berg') {
            if ($ort === 'see' && isset($zones['medrig'])) {
                $zone = 'medrig';
            } elseif ($ort === 'kappl' && isset($zones['dias'])) {
                $zone = 'dias';
            } elseif ($ort === 'ischgl' && isset($zones['idalp'])) {
                $zone = 'idalp';
            }
        }
        if (! is_string($zone) || ! isset($zones[$zone])) {
            $zone = isset($zones['tal']) ? 'tal' : (string) array_key_first($zones);
        }
        $spot = $zones[$zone];

        return [
            'id' => $ort.'-'.$zone,
            'ort' => $ort,
            'zone' => $zone,
            'label' => (string) ($row['label'] ?? ''),
            'lat' => (float) ($spot['lat'] ?? 0),
            'lon' => (float) ($spot['lon'] ?? 0),
            'asl' => (int) ($spot['asl'] ?? 0),
            'zones' => array_keys($zones),
        ];
    }

    public function forecast(?string $ort = null, ?string $zone = null): array
    {
        $place = $this->resolve($ort, $zone);
        $key = (string) config('weather.api_key');
        if ($key === '') {
            return ['ok' => false, 'error' => 'Kein Wetterschlüssel hinterlegt.', 'place' => $place['label']];
        }

        $cacheKey = sprintf(
            'weather.meteoblue.v9.%s.%s.%s.%d',
            $place['id'],
            $place['lat'],
            $place['lon'],
            $place['asl']
        );
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $this->keepUntilNextHour($cacheKey, $cached);

            return $this->present($cached, $place['asl']);
        }

        try {
            $fresh = Cache::lock($cacheKey.'.lock', 25)->block(20, function () use ($cacheKey, $key, $place) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }

                $fresh = $this->download($key, $place);
                Cache::put($cacheKey, $fresh, $this->freshUntil());

                return $fresh;
            });
        } catch (LockTimeoutException $e) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $this->present($cached, $place['asl']);
            }

            return ['ok' => false, 'error' => 'Wetterdienst nicht erreichbar.', 'place' => $place['label']];
        } catch (Throwable $e) {
            Log::warning('meteoblue request failed', ['message' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Wetterdienst nicht erreichbar.', 'place' => $place['label']];
        }

        return $this->present($fresh, $place['asl']);
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
                $this->paperRain((string) ($day['rain_text'] ?? '–')),
                $day['sun_text'] ?? '-',
                $day['wind_text'] ?? '-',
                implode(',', $heights),
                $this->paperIcon((string) ($day['morning']['icon'] ?? '')),
                $this->paperIcon((string) ($day['afternoon']['icon'] ?? '')),
                $this->paperIcon((string) ($day['night']['icon'] ?? '')),
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
                $hourLines[] = substr((string) $hour['time'], 0, 2)
                    .'  '.$this->paperIcon((string) ($hour['icon'] ?? ''))
                    .'  '.$hour['temp']
                    .'  '.$this->paperRain((string) ($hour['rain_text'] ?? '–'))
                    .'  '.(int) ($hour['rain_height'] ?? 0);
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

    /** @param  array{id:string,label:string,lat:float,lon:float,asl:int}  $place */
    private function download(string $key, array $place): array
    {
        $response = Http::timeout(20)->acceptJson()->get('https://my.meteoblue.com/packages/basic-1h_basic-day_clouds-day_air-day_air-3h', [
            'lat' => $place['lat'],
            'lon' => $place['lon'],
            'asl' => $place['asl'],
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

        return $this->normalize($response->json(), $place['label']);
    }

    private function normalize(array $raw, string $place): array
    {
        $hours = $raw['data_1h'] ?? [];
        $days = $raw['data_day'] ?? [];
        $meta = $raw['metadata'] ?? [];
        $freezeByHour = $this->freezeLevelsByHour($raw['data_3h'] ?? []);
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
            $rain = round((float) ($days['precipitation'][$i] ?? 0), 1);
            $pop = (int) round((float) ($days['precipitation_probability'][$i] ?? 0));
            $meanWind = (float) ($days['windspeed_mean'][$i] ?? $days['windspeed_max'][$i] ?? 0);

            $hourRows = [];
            $snowWeights = [];
            for ($h = $start; $h <= $end; $h++) {
                $stamp = (string) ($hours['time'][$h] ?? '');
                $code = (int) ($hours['pictocode'][$h] ?? $dayCode);
                $hourLook = $this->look($code);
                $isDay = isset($hours['isdaylight'][$h]) ? ((int) $hours['isdaylight'][$h] === 1) : null;
                if ($isDay === false) {
                    $hourLook['icon'] = $this->nightSymbol($hourLook['icon']);
                }
                $mm = round((float) ($hours['precipitation'][$h] ?? 0), 1);
                $freeze = $freezeByHour[substr($stamp, 0, 13)] ?? null;
                $snowWeights[] = ['rain' => $mm, 'freeze_m' => $freeze];
                $hourRows[] = [
                    'time' => substr($stamp, 11, 5),
                    'snow_m' => $mm > 0 ? $this->snowLine($freeze) : null,
                    'temp' => (int) round((float) ($hours['temperature'][$h] ?? 0)),
                    'feel' => (int) round((float) ($hours['felttemperature'][$h] ?? 0)),
                    'rain' => $mm,
                    'pop' => (int) round((float) ($hours['precipitation_probability'][$h] ?? 0)),
                    'wind' => (int) round((float) ($hours['windspeed'][$h] ?? 0)),
                    'wind_dir' => $this->compass((float) ($hours['winddirection'][$h] ?? 0)),
                    'icon' => $hourLook['icon'],
                    'label' => $hourLook['label'],
                    'terminal' => $hourLook['terminal'],
                    'daylight' => $isDay,
                    'sky' => $this->sky($code),
                    'is_now' => substr($stamp, 0, 13) === $nowKey,
                ];
            }

            $snow = $this->snowLineForDay($snowWeights);
            $look = $this->dayLook($hourRows, $rain, $days['sunshine_time'][$i] ?? null, $dayCode);
            $halves = $this->dayHalves($hourRows, $days['sunshine_time'][$i] ?? null, $look);
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
                'snow_m' => $snow['snow_m'],
                'snow_weighted' => $snow['weighted'],
                'icon' => $look['icon'],
                'label' => $halves['morning']['label'] === $halves['afternoon']['label']
                    ? $look['label']
                    : $halves['morning']['label'].', später '.$halves['afternoon']['label'],
                'terminal' => $look['terminal'],
                'morning' => $halves['morning'],
                'afternoon' => $halves['afternoon'],
                'night' => null,
                'hours' => $hourRows,
            ];
        }

        foreach ($outDays as $i => $outDay) {
            $outDays[$i]['night'] = $this->nightLook($outDay['hours'], $outDays[$i + 1]['hours'] ?? []);
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
            'place' => $place,
            'updated' => (string) ($meta['modelrun_updatetime_utc'] ?? ''),
            'current' => $current,
            'days' => $outDays,
        ];
    }

    private function present(array $data, int $asl = 0): array
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
        $showSnow = false;
        foreach ($data['days'] as $day) {
            $date = (string) ($day['date'] ?? '');
            if ($date !== '' && $date < $today) {
                continue;
            }
            $when = new \DateTimeImmutable($date !== '' ? $date : 'now', $tz);
            $index = count($days);
            $day['name'] = $index === 0 ? 'Heute' : ($index === 1 ? 'Morgen' : $names[(int) $when->format('w')]);
            $day['short'] = $index === 0 ? 'Heute' : $week[(int) $when->format('w')];
            $hours = $day['hours'] ?? [];
            foreach ($hours as $h => $hour) {
                $mm = (float) ($hour['rain'] ?? 0);
                $hours[$h]['rain_height'] = $this->rainHeight($mm);
                if ($mm <= 0) {
                    $hours[$h]['snow_m'] = null;
                }
                $hours[$h]['rain_text'] = $this->rainText($mm, $this->fallsAsSnow($hours[$h]['snow_m'] ?? null, $asl));
                $stamp = $date.' '.substr((string) ($hour['time'] ?? ''), 0, 2);
                $hours[$h]['is_now'] = $stamp === $nowKey;
                if ($hours[$h]['is_now']) {
                    $current = $hours[$h];
                }
                if (($hours[$h]['snow_m'] ?? null) !== null) {
                    $showSnow = true;
                }
            }
            $day['hours'] = $hours;
            $day['rain_slots'] = $this->rainSlots($hours, $asl);
            if (empty($day['snow_weighted'])) {
                $day['snow_m'] = null;
            }
            $day['rain_text'] = $this->rainText((float) ($day['rain'] ?? 0), $this->fallsAsSnow($day['snow_m'] ?? null, $asl));
            if (($day['snow_m'] ?? null) !== null) {
                $showSnow = true;
            }
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

        $data['days'] = $days;
        $data['current'] = $current;
        $data['show_snow'] = $showSnow;

        return $data;
    }

    /**
     * Schneefallgrenze je Stunde. Die freie Schnittstelle liefert die
     * Nullgradgrenze alle drei Stunden. Dieselbe Höhe gilt für diese Stunde
     * und die zwei folgenden.
     *
     * @param  array<string, mixed>  $data3h
     * @return array<string, ?int> Schlüssel „Y-m-d H“
     */
    private function snowLevelsByHour(array $data3h): array
    {
        $out = [];
        foreach ($this->freezeLevelsByHour($data3h) as $key => $level) {
            $out[$key] = $this->snowLine($level);
        }

        return $out;
    }

    /**
     * Nullgradgrenze je Stunde, in Metern. Dieselbe Höhe gilt für die
     * gemeldete Stunde und die zwei folgenden.
     *
     * @param  array<string, mixed>  $data3h
     * @return array<string, ?float> Schlüssel „Y-m-d H“
     */
    private function freezeLevelsByHour(array $data3h): array
    {
        $times = $data3h['time'] ?? [];
        $levels = $data3h['freezinglevelheight'] ?? [];
        if (! is_array($times) || ! is_array($levels)) {
            return [];
        }

        $out = [];
        foreach ($times as $i => $stamp) {
            if (! preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2})/', (string) $stamp, $match)) {
                continue;
            }
            $level = $levels[$i] ?? null;
            $stored = is_numeric($level) ? (float) $level : null;
            $day = $match[1];
            $hour = (int) $match[2];
            for ($step = 0; $step < 3; $step++) {
                $slot = $hour + $step;
                $date = $day;
                if ($slot >= 24) {
                    $slot -= 24;
                    $date = (new \DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
                }
                $out[sprintf('%s %02d', $date, $slot)] = $stored;
            }
        }

        return $out;
    }

    /**
     * Tageswert nur, wenn Niederschlag fällt. Jede nasse Stunde zählt mit
     * ihrem Literanteil. Ein trockener Tag bleibt leer.
     *
     * @param  list<array{rain: float|int, freeze_m: mixed}>  $hours
     * @return array{snow_m: ?int, weighted: bool}
     */
    private function snowLineForDay(array $hours): array
    {
        $sum = 0.0;
        $weight = 0.0;
        foreach ($hours as $hour) {
            $mm = (float) ($hour['rain'] ?? 0);
            $level = $hour['freeze_m'] ?? null;
            if ($mm <= 0 || ! is_numeric($level)) {
                continue;
            }
            $sum += (float) $level * $mm;
            $weight += $mm;
        }
        if ($weight <= 0) {
            return ['snow_m' => null, 'weighted' => false];
        }

        return ['snow_m' => $this->snowLine($sum / $weight), 'weighted' => true];
    }

    /**
     * Schneefallgrenze in Metern, auf 100 m gerundet.
     * null, wenn sie fehlt oder bei 3000 m und darüber liegt.
     */
    private function snowLine(mixed $freezingLevel): ?int
    {
        if (! is_numeric($freezingLevel)) {
            return null;
        }

        $meters = (int) (round((((float) $freezingLevel) - self::SNOW_LINE_BELOW_FREEZING_M) / 100) * 100);
        if ($meters < 0) {
            $meters = 0;
        }
        if ($meters >= self::SNOW_LINE_LIMIT_M) {
            return null;
        }

        return $meters;
    }

    private function rainSlots(array $hours, int $asl = 0): array
    {
        $blocks = array_fill(0, 8, 0.0);
        $snowOnly = array_fill(0, 8, true);
        $wet = array_fill(0, 8, false);
        foreach ($hours as $hour) {
            $clock = (int) substr((string) ($hour['time'] ?? ''), 0, 2);
            $slot = min(7, intdiv($clock, 3));
            $mm = (float) ($hour['rain'] ?? 0);
            $blocks[$slot] += $mm;
            if ($mm <= 0) {
                continue;
            }
            $wet[$slot] = true;
            if (! $this->fallsAsSnow($hour['snow_m'] ?? null, $asl)) {
                $snowOnly[$slot] = false;
            }
        }
        $labels = ['0–3 Uhr', '3–6 Uhr', '6–9 Uhr', '9–12 Uhr', '12–15 Uhr', '15–18 Uhr', '18–21 Uhr', '21–24 Uhr'];
        $slots = [];
        foreach ($blocks as $i => $mm) {
            $mm = round($mm, 1);
            $slots[] = [
                'label' => $labels[$i],
                'mm' => $mm,
                'text' => $this->rainText($mm, $wet[$i] && $snowOnly[$i]),
                'height' => $this->rainHeight($mm),
            ];
        }

        return $slots;
    }

    private function paperRain(string $text): string
    {
        return str_replace(' L/m²', ' L', $text);
    }

    private function paperIcon(string $icon): string
    {
        return match ($icon) {
            'bedtime' => 'nacht',
            'partly_cloudy_night' => 'nacht-wolkig',
            'thunderstorm' => 'gewitter',
            'weather_snowy', 'weather_mix' => 'schnee',
            'rainy' => 'regen',
            'foggy' => 'nebel',
            'cloud' => 'bewoelkt',
            'partly_cloudy_day' => 'wolkig',
            default => $icon === 'sunny' ? 'sonne' : '',
        };
    }

    private function rainHeight(float $mm): int
    {
        if ($mm <= 0) {
            return 0;
        }

        return (int) min(100, max(1, round($mm / self::RAIN_FULL_MM * 100)));
    }

    /** Schneefallgrenze unter dem Messpunkt: dort fällt der Niederschlag als Schnee. */
    private function fallsAsSnow(mixed $snowM, int $asl): bool
    {
        return is_numeric($snowM) && (int) $snowM < $asl;
    }

    private function rainText(float $mm, bool $asSnow = false): string
    {
        if ($mm <= 0) {
            return '–';
        }
        $amount = $asSnow ? $mm * self::SNOW_CM_PER_MM : $mm;
        $unit = $asSnow ? 'cm' : 'L/m²';
        if (abs($amount - round($amount)) < 0.05) {
            return ((int) round($amount)).' '.$unit;
        }

        return number_format($amount, 1, ',', '').' '.$unit;
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

    /**
     * Symbol für den ganzen Tag. Der Tagescode von meteoblue ist zu grob und
     * hat eine andere Skala als der Stundencode, deshalb zählen die echten
     * Werte: erst Niederschlag am Tag, dann Nebel, sonst der Anteil der
     * möglichen Sonnenzeit, der wirklich scheint.
     */
    private function dayLook(array $rows, float $rain, mixed $sunMinutes, int $dayCode): array
    {
        [, $day] = $this->splitDay($rows);
        if ($day === []) {
            return $this->look($dayCode);
        }

        $sun = $sunMinutes === null || $sunMinutes === ''
            ? null
            : min(1.0, ((float) $sunMinutes) / (count($day) * 60));

        return $this->phaseLook($day, $rain, $sun);
    }

    /**
     * Erste und zweite Tageshälfte, getrennt am Mittelpunkt zwischen
     * Sonnenaufgang und Sonnenuntergang. Die Sonnenscheindauer gibt es nur
     * für den ganzen Tag. Der Stundenverlauf liefert die Form, die Tagesdauer
     * eicht sie, damit eine einzelne Sonnenstunde nicht als Sonne zählt.
     *
     * @return array{morning: array, afternoon: array}
     */
    private function dayHalves(array $rows, mixed $sunMinutes, array $fallback): array
    {
        [, $day] = $this->splitDay($rows);
        $count = count($day);
        if ($count === 0) {
            return ['morning' => $fallback, 'afternoon' => $fallback];
        }

        $first = array_slice($day, 0, (int) ceil($count / 2));
        $second = array_slice($day, (int) floor($count / 2));

        $known = $sunMinutes !== null && $sunMinutes !== '';
        $shape = 0.0;
        foreach ($day as $row) {
            $shape += $this->rowSky($row);
        }
        $actual = $known ? ((float) $sunMinutes) / 60 : 0.0;
        $scale = $shape > 0 ? min(2.0, $actual / $shape) : 0.0;

        $sunOf = function (array $part) use ($known, $shape, $scale, $actual, $count): ?float {
            if (! $known) {
                return null;
            }
            if ($shape <= 0) {
                return min(1.0, $actual / $count);
            }
            $sum = 0.0;
            foreach ($part as $row) {
                $sum += $this->rowSky($row);
            }

            return min(1.0, $scale * $sum / count($part));
        };

        $mm = fn (array $part): float => array_sum(array_map(fn ($row) => (float) ($row['rain'] ?? 0), $part));

        return [
            'morning' => $this->phaseLook($first, $mm($first), $sunOf($first)),
            'afternoon' => $this->phaseLook($second, $mm($second), $sunOf($second)),
        ];
    }

    /**
     * Nacht von Sonnenuntergang bis zum Sonnenaufgang des Folgetags.
     */
    private function nightLook(array $rows, array $nextRows): ?array
    {
        [, , $evening] = $this->splitDay($rows);
        [$morning] = $nextRows === [] ? [[]] : $this->splitDay($nextRows);
        $night = array_merge($evening, $morning);
        if ($night === []) {
            return null;
        }

        $mm = array_sum(array_map(fn ($row) => (float) ($row['rain'] ?? 0), $night));
        $look = $this->phaseLook($night, $mm, null);

        return [
            'icon' => $this->nightSymbol($look['icon']),
            'label' => 'Nacht, '.$look['label'],
        ];
    }

    /**
     * Teilt die Stunden eines Tages in Stunden vor Sonnenaufgang, Tageslicht und
     * Stunden nach Sonnenuntergang. Ohne Tageslicht-Flag gilt 07 bis 20 Uhr.
     *
     * @return array{0: array, 1: array, 2: array}
     */
    private function splitDay(array $rows): array
    {
        $flagged = false;
        foreach ($rows as $row) {
            if (($row['daylight'] ?? null) !== null) {
                $flagged = true;
                break;
            }
        }

        $before = $day = $after = [];
        foreach ($rows as $row) {
            if ($flagged) {
                $isDay = ($row['daylight'] ?? null) === true;
            } else {
                $clock = (int) substr((string) ($row['time'] ?? ''), 0, 2);
                $isDay = $clock >= 7 && $clock <= 20;
            }
            if ($isDay) {
                $day[] = $row;
            } elseif ($day === []) {
                $before[] = $row;
            } else {
                $after[] = $row;
            }
        }

        return [$before, $day, $after];
    }

    /**
     * Symbol für einen Abschnitt aus Stundenwerten. $sun ist der Anteil
     * (0 bis 1) der Zeit mit echtem Sonnenschein, null heißt: aus dem
     * Wolkenbild der Stunden schätzen.
     */
    private function phaseLook(array $rows, float $mm, ?float $sun): array
    {
        $count = count($rows);
        $wet = ['thunderstorm' => 0, 'weather_snowy' => 0, 'rainy' => 0];
        $wetHours = 0;
        $fog = 0;
        $sky = 0.0;
        foreach ($rows as $row) {
            $icon = (string) ($row['icon'] ?? 'sunny');
            if (($row['rain'] ?? 0) >= 0.2) {
                $wetHours++;
                if (isset($wet[$icon])) {
                    $wet[$icon]++;
                } else {
                    $wet['rainy']++;
                }
            }
            if ($icon === 'foggy') {
                $fog++;
            }
            $sky += $this->rowSky($row);
        }

        if ($mm >= max(0.3, $count / 13) || $wetHours >= max(2, (int) round($count / 4))) {
            if ($wet['thunderstorm'] > 0) {
                return $this->look(27);
            }
            if ($wet['weather_snowy'] > $wet['rainy']) {
                return $this->look(24);
            }

            return $this->look(23);
        }

        if ($fog * 2 >= $count) {
            return $this->look(16);
        }

        $share = $sun ?? $sky / $count;
        if ($share >= 0.65) {
            return $this->look(1);
        }
        if ($share >= 0.25) {
            return $this->look(7);
        }

        return $this->look(19);
    }

    private function rowSky(array $row): float
    {
        if (isset($row['sky'])) {
            return (float) $row['sky'];
        }

        return match ((string) ($row['icon'] ?? 'sunny')) {
            'sunny', 'bedtime' => 1.0,
            'partly_cloudy_day', 'partly_cloudy_night' => 0.5,
            'cloud', 'foggy' => 0.1,
            default => 0.0,
        };
    }

    /** Wie klar der Himmel laut Stundencode ist, 1 = wolkenlos, 0 = zu. */
    private function sky(int $code): float
    {
        return match (true) {
            $code >= 1 && $code <= 3 => 1.0,
            $code >= 4 && $code <= 6 => 0.9,
            $code >= 7 && $code <= 12 => 0.5,
            $code >= 13 && $code <= 15 => 0.85,
            $code >= 16 && $code <= 18 => 0.1,
            $code >= 19 && $code <= 21 => 0.2,
            default => 0.0,
        };
    }

    private function nightSymbol(string $icon): string
    {
        return match ($icon) {
            'sunny' => 'bedtime',
            'partly_cloudy_day' => 'partly_cloudy_night',
            default => $icon,
        };
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
