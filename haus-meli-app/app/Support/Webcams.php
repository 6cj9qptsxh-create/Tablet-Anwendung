<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class Webcams
{
    /**
     * @return array{name: string, place: string, asl: int|null, page: string, image: string, source: string, time: string, video: string}|null
     */
    public static function forSpot(string $ort, string $zone): ?array
    {
        $all = config('webcams');
        $cam = is_array($all) ? ($all[$ort][$zone] ?? null) : null;
        if (! is_array($cam)) {
            return null;
        }

        $name = trim((string) ($cam['name'] ?? ''));
        $page = trim((string) ($cam['page'] ?? ''));
        $image = trim((string) ($cam['image'] ?? ''));
        if ($name === '' || $page === '' || $image === '') {
            return null;
        }

        $host = (string) parse_url($page, PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', $host) ?: '';
        $asl = $cam['asl'] ?? null;
        $feratel = $cam['feratel'] ?? null;
        $video = is_numeric($feratel) ? self::latestClip((int) $feratel) : '';

        return [
            'name' => $name,
            'place' => trim((string) ($cam['place'] ?? '')),
            'asl' => is_numeric($asl) ? (int) $asl : null,
            'page' => $page,
            'image' => $image,
            'source' => $host,
            'time' => self::updatedLabel($image),
            'video' => $video,
        ];
    }

    /**
     * Neueste Feratel-Datei dieser Kamera. Die Seite enthält auch den Verlauf, den lesen wir nicht aus.
     */
    public static function latestClip(int $cam): string
    {
        if ($cam < 1) {
            return '';
        }

        $key = 'webcam.clip.v2.'.$cam;
        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }

        $url = self::fetchClip($cam);
        Cache::put($key, $url, $url === '' ? 20 : 120);

        return $url;
    }

    private static function fetchClip(int $cam): string
    {
        $page = 'https://webtv.feratel.com/webtv/?'.http_build_query([
            'design' => 'v5',
            'cam' => $cam,
            'lg' => 'de',
        ]);

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                    'Accept' => 'text/html',
                ])
                ->get($page);
        } catch (Throwable) {
            return '';
        }
        if (! $response->ok()) {
            return '';
        }
        if (! preg_match('#https://sts\d+\.feratel\.co\.at/streams/[A-Za-z0-9_./-]+Vid\.mp4#', $response->body(), $match)) {
            return '';
        }
        $url = $match[0];

        return str_contains($url, 'dcsdesign=') ? $url : $url.'?dcsdesign=feratel4';
    }

    public static function updatedLabel(string $image): string
    {
        $header = Cache::remember('webcam.lm.'.sha1($image), 120, function () use ($image) {
            try {
                $response = Http::timeout(2)->head($image);
            } catch (Throwable) {
                return '';
            }
            $value = $response->header('Last-Modified');

            return is_string($value) ? $value : '';
        });

        if (! is_string($header) || $header === '') {
            return '';
        }

        try {
            $at = Carbon::parse($header)->timezone((string) config('weather.timezone', 'Europe/Vienna'));
        } catch (Throwable) {
            return '';
        }

        if ($at->isToday()) {
            return $at->format('H:i').' Uhr';
        }

        return $at->format('d.m. H:i').' Uhr';
    }
}
