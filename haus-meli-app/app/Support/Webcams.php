<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class Webcams
{
    /**
     * @return array{name: string, place: string, asl: int|null, page: string, image: string, source: string, time: string}|null
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

        return [
            'name' => $name,
            'place' => trim((string) ($cam['place'] ?? '')),
            'asl' => is_numeric($asl) ? (int) $asl : null,
            'page' => $page,
            'image' => $image,
            'source' => $host,
            'time' => self::updatedLabel($image),
        ];
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
