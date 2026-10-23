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
        $direct = trim((string) ($cam['video'] ?? ''));
        $feratel = $cam['feratel'] ?? null;
        if ($direct !== '') {
            $clip = self::directClip($direct);
        } else {
            $clip = is_numeric($feratel) ? self::clip((int) $feratel) : ['url' => '', 'taken' => ''];
        }
        $archive = trim((string) ($cam['archive'] ?? ''));
        $time = $clip['taken'] !== '' ? self::labelFromUtc($clip['taken']) : self::imageLabel($image, $archive);

        return [
            'name' => $name,
            'place' => trim((string) ($cam['place'] ?? '')),
            'asl' => is_numeric($asl) ? (int) $asl : null,
            'page' => $page,
            'image' => $image,
            'source' => $host,
            'time' => $time,
            'video' => $clip['url'],
        ];
    }

    /**
     * Fest hinterlegte Videodatei. Die Datei trägt keine Aufnahmezeit,
     * deshalb gilt der Zeitpunkt, an dem sie geschrieben wurde.
     *
     * @return array{url: string, taken: string}
     */
    private static function directClip(string $url): array
    {
        $empty = ['url' => '', 'taken' => ''];
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === '') {
            return $empty;
        }

        $key = 'webcam.file.v1.'.sha1($url);
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['url'], $cached['taken'])) {
            return ['url' => (string) $cached['url'], 'taken' => (string) $cached['taken']];
        }

        $taken = self::fileWritten($url);
        $clip = [
            'url' => $taken === '' ? $url : $url.(str_contains($url, '?') ? '&' : '?').'v='.substr(sha1($taken), 0, 12),
            'taken' => $taken,
        ];
        Cache::put($key, $clip, $taken !== '' ? 120 : 20);

        return $clip;
    }

    private static function fileWritten(string $url): string
    {
        try {
            $response = Http::timeout(8)
                ->withOptions(['curl' => [CURLOPT_RANGE => '-4096']])
                ->withHeaders(self::browserHeaders('video/mp4'))
                ->get($url);
        } catch (Throwable) {
            return '';
        }
        if (! $response->successful()) {
            return '';
        }
        $modified = $response->header('Last-Modified');
        if (! is_string($modified) || $modified === '') {
            return '';
        }

        try {
            return Carbon::parse($modified)->utc()->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Neueste Feratel-Datei und ihre Aufnahmezeit in UTC.
     * Die Playerseite enthält auch den Verlauf, den lesen wir nicht aus.
     *
     * @return array{url: string, taken: string}
     */
    private static function clip(int $cam): array
    {
        if ($cam < 1) {
            return ['url' => '', 'taken' => ''];
        }

        $key = 'webcam.clip.v3.'.$cam;
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['url'], $cached['taken'])) {
            return ['url' => (string) $cached['url'], 'taken' => (string) $cached['taken']];
        }

        $clip = self::fetchClip($cam);
        Cache::put($key, $clip, $clip['url'] !== '' && $clip['taken'] !== '' ? 120 : 20);

        return $clip;
    }

    /**
     * @return array{url: string, taken: string}
     */
    private static function fetchClip(int $cam): array
    {
        $empty = ['url' => '', 'taken' => ''];
        $page = 'https://webtv.feratel.com/webtv/?'.http_build_query([
            'design' => 'v5',
            'cam' => $cam,
            'lg' => 'de',
        ]);

        try {
            $response = Http::timeout(8)
                ->withHeaders(self::browserHeaders('text/html'))
                ->get($page);
        } catch (Throwable) {
            return $empty;
        }
        if (! $response->ok()) {
            return $empty;
        }
        if (! preg_match('#https://sts\d+\.feratel\.co\.at/streams/[A-Za-z0-9_./-]+Vid\.mp4#', $response->body(), $match)) {
            return $empty;
        }
        $url = $match[0];
        if (! str_contains($url, 'dcsdesign=')) {
            $url .= '?dcsdesign=feratel4';
        }

        return ['url' => $url, 'taken' => self::clipTaken($url)];
    }

    /**
     * Feratel schreibt die Aufnahmezeit in UTC in den Dateikopf, zum Beispiel "2026-10-22 18:22:16".
     */
    private static function clipTaken(string $url): string
    {
        try {
            $response = Http::timeout(8)
                ->withOptions(['curl' => [CURLOPT_RANGE => '0-524287']])
                ->withHeaders(self::browserHeaders('video/mp4'))
                ->get($url);
        } catch (Throwable) {
            return '';
        }
        if (! $response->successful()) {
            return '';
        }
        if (! preg_match('/"date"\s*:\s*"(20\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})"/', $response->body(), $match)) {
            return '';
        }

        return $match[1];
    }

    /**
     * Uhrzeit der Aufnahme. Zuerst die Zeit im Bild, dann der erste Archivtreffer
     * mit genau diesem Bild. Erst danach der Zeitpunkt, an dem die Datei abgelegt wurde.
     */
    public static function imageLabel(string $image, string $archive = ''): string
    {
        $header = Cache::remember('webcam.shot.v2.'.sha1($image.'|'.$archive), 120, function () use ($image, $archive) {
            $archived = $archive !== '' ? self::archiveTaken($image, $archive) : '';
            if ($archived !== '') {
                return 'local|'.$archived;
            }

            try {
                $response = Http::timeout(4)
                    ->withOptions(['curl' => [CURLOPT_RANGE => '0-65535']])
                    ->withHeaders(self::browserHeaders('image/jpeg'))
                    ->get($image);
            } catch (Throwable) {
                return '';
            }
            if (! $response->successful()) {
                return '';
            }
            if (preg_match('/(20\d{2}:\d{2}:\d{2} \d{2}:\d{2}:\d{2})/', $response->body(), $match)) {
                return 'exif|'.$match[1];
            }
            $modified = $response->header('Last-Modified');

            return is_string($modified) && $modified !== '' ? 'file|'.$modified : '';
        });

        if (! is_string($header) || ! str_contains($header, '|')) {
            return '';
        }
        [$kind, $raw] = explode('|', $header, 2);
        $zone = (string) config('weather.timezone', 'Europe/Vienna');

        try {
            $at = match ($kind) {
                'exif' => Carbon::createFromFormat('Y:m:d H:i:s', $raw, $zone),
                'local' => Carbon::createFromFormat('Y-m-d H:i:s', $raw, $zone),
                default => Carbon::parse($raw)->timezone($zone),
            };
        } catch (Throwable) {
            return '';
        }
        if ($at === false) {
            return '';
        }

        return self::formatTaken($at);
    }

    /**
     * Frühester Archivslot, dessen Datei noch dasselbe Bild ist.
     * Spätere Slots sind oft nur dieselbe Aufnahme noch einmal abgelegt.
     */
    private static function archiveTaken(string $image, string $archive): string
    {
        $parts = parse_url($image);
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        if (! in_array($scheme, ['https'], true) || $host === '' || ! str_starts_with($archive, '/var/webcams/archive/')) {
            return '';
        }
        $origin = $scheme.'://'.$host;
        $zone = (string) config('weather.timezone', 'Europe/Vienna');
        $date = Carbon::now($zone)->format('Y-m-d');

        try {
            $list = Http::timeout(4)
                ->withHeaders(self::browserHeaders('application/json'))
                ->get($origin.'/de/webcam-archive', ['date' => $date, 'archive' => $archive]);
            $current = Http::timeout(4)
                ->withHeaders(self::browserHeaders('image/jpeg'))
                ->get($image);
        } catch (Throwable) {
            return '';
        }
        $images = $list->json('images');
        if (! $list->ok() || ! is_array($images) || ! $current->successful()) {
            return '';
        }
        $currentHash = md5($current->body());
        $found = '';
        $checked = 0;
        foreach (array_reverse($images, true) as $time => $path) {
            if ($checked >= 12) {
                break;
            }
            $checked++;
            if (! is_string($time) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
                continue;
            }
            if (! is_string($path) || ! str_starts_with($path, $archive.'/') || ! str_ends_with($path, '.jpg')) {
                break;
            }
            try {
                $shot = Http::timeout(4)->withHeaders(self::browserHeaders('image/jpeg'))->get($origin.$path);
            } catch (Throwable) {
                break;
            }
            if (! $shot->successful() || md5($shot->body()) !== $currentHash) {
                break;
            }
            $found = $date.' '.$time.':00';
        }

        return $found;
    }

    private static function labelFromUtc(string $raw): string
    {
        try {
            $at = Carbon::createFromFormat('Y-m-d H:i:s', $raw, 'UTC');
        } catch (Throwable) {
            return '';
        }
        if ($at === false) {
            return '';
        }

        return self::formatTaken($at);
    }

    private static function formatTaken(Carbon $at): string
    {
        $at = $at->timezone((string) config('weather.timezone', 'Europe/Vienna'));
        if ($at->isToday()) {
            return $at->format('H:i').' Uhr';
        }

        return $at->format('d.m. H:i').' Uhr';
    }

    /**
     * @return array<string, string>
     */
    private static function browserHeaders(string $accept): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'Accept' => $accept,
        ];
    }
}
