<?php

namespace App\Services;

/**
 * Grobe Dauer-Schätzung aus Sportart, Distanz und Höhenmeter.
 * Formel bewusst einfach und mit dem Guest-JS synchron halten.
 */
class TourDurationEstimator
{
    /** @var array<string, array{speed: float, hm_per_100: float}> */
    public const MODE_PARAMS = [
        'hike' => ['speed' => 4.0, 'hm_per_100' => 10.0],
        'bike' => ['speed' => 12.0, 'hm_per_100' => 6.0],
        'ebike' => ['speed' => 15.0, 'hm_per_100' => 3.0],
        'ski' => ['speed' => 8.0, 'hm_per_100' => 5.0],
        'sled' => ['speed' => 6.0, 'hm_per_100' => 4.0],
    ];

    public const CABLE_DEFAULT_MIN = 15;

    public static function estimateMinutes(string $mode, float $distanceKm, int $elevationM, bool $isCable = false): int
    {
        if ($isCable || $mode === 'cable') {
            return self::CABLE_DEFAULT_MIN;
        }

        $params = self::MODE_PARAMS[$mode] ?? self::MODE_PARAMS['hike'];
        $speed = max(0.5, (float) $params['speed']);
        $fromDist = ((float) $distanceKm / $speed) * 60.0;
        $fromHm = ((int) $elevationM / 100.0) * (float) $params['hm_per_100'];

        return max(1, (int) round($fromDist + $fromHm));
    }

    /**
     * Wenn duration_min fehlt → schätzen; sonst vorhandenen Wert behalten.
     */
    public static function resolveMinutes(?int $stored, string $mode, float $distanceKm, int $elevationM, bool $isCable = false): int
    {
        if ($stored !== null && $stored > 0) {
            return $stored;
        }

        return self::estimateMinutes($mode, $distanceKm, $elevationM, $isCable);
    }
}
