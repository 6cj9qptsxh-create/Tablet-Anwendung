<?php

namespace App\Services;

use App\Models\TourSegment;
use App\Models\TripVariant;
use Illuminate\Support\Collection;

class GpxService
{
    /**
     * GPX-XML parsen → GeoJSON LineString + Stats.
     *
     * @return array{geojson: array, distance_km: float, elevation_m: int, point_count: int}
     */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new \InvalidArgumentException('Ungültige GPX-Datei.');
        }

        $doc->registerXPathNamespace('g', 'http://www.topografix.com/GPX/1/1');
        $doc->registerXPathNamespace('g10', 'http://www.topografix.com/GPX/1/0');

        $nodes = $doc->xpath('//g:trkpt|//g10:trkpt|//trkpt|//g:rtept|//g10:rtept|//rtept');
        if ($nodes === false || $nodes === []) {
            throw new \InvalidArgumentException('Keine Trackpunkte in der GPX-Datei gefunden.');
        }

        $points = [];
        foreach ($nodes as $n) {
            $lat = (float) $n['lat'];
            $lng = (float) $n['lon'];
            $ele = isset($n->ele) ? (float) $n->ele : null;
            $points[] = [
                'lat' => $lat,
                'lng' => $lng,
                'ele' => $ele,
            ];
        }

        return $this->statsFromPoints($points);
    }

    /**
     * @param  list<array{lat: float, lng: float, ele: ?float}>  $points
     * @return array{geojson: array, distance_km: float, elevation_m: int, point_count: int}
     */
    public function statsFromPoints(array $points): array
    {
        $coords = [];
        $distanceM = 0.0;
        $elevGain = 0.0;
        $prev = null;

        foreach ($points as $p) {
            // GeoJSON Position: [lng, lat] oder [lng, lat, ele]
            $coords[] = $p['ele'] !== null
                ? [$p['lng'], $p['lat'], $p['ele']]
                : [$p['lng'], $p['lat']];
            if ($prev !== null) {
                $distanceM += $this->haversineM($prev['lat'], $prev['lng'], $p['lat'], $p['lng']);
                if ($prev['ele'] !== null && $p['ele'] !== null) {
                    $delta = $p['ele'] - $prev['ele'];
                    if ($delta > 0.5) {
                        $elevGain += $delta;
                    }
                }
            }
            $prev = $p;
        }

        return [
            'geojson' => [
                'type' => 'LineString',
                'coordinates' => $coords,
            ],
            'distance_km' => round($distanceM / 1000, 2),
            'elevation_m' => (int) round($elevGain),
            'point_count' => count($coords),
        ];
    }

    public function combineVariantToGpx(TripVariant $variant): string
    {
        $variant->loadMissing('segments', 'trip');
        $name = ($variant->trip?->localized('title') ?? 'Tour') . ' — ' . $variant->name;

        $trkppts = '';
        foreach ($variant->segments as $seg) {
            $reversed = (bool) ($seg->pivot->reversed ?? false);
            foreach ($seg->coordinates($reversed) as $c) {
                $lng = $c[0];
                $lat = $c[1];
                $ele = isset($c[2]) && is_numeric($c[2]) ? (float) $c[2] : null;
                $eleXml = $ele !== null ? '<ele>' . round($ele, 1) . '</ele>' : '';
                $trkppts .= "      <trkpt lat=\"{$lat}\" lon=\"{$lng}\">{$eleXml}</trkpt>\n";
            }
        }

        $safeName = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Haus Meli" xmlns="http://www.topografix.com/GPX/1/1">
  <metadata><name>{$safeName}</name></metadata>
  <trk>
    <name>{$safeName}</name>
    <trkseg>
{$trkppts}    </trkseg>
  </trk>
</gpx>
XML;
    }

    public function segmentToGpx(TourSegment $segment): string
    {
        $safeName = htmlspecialchars($segment->name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $trkppts = '';
        foreach ($segment->coordinates(false) as $c) {
            $ele = isset($c[2]) && is_numeric($c[2]) ? (float) $c[2] : null;
            $eleXml = $ele !== null ? '<ele>' . round($ele, 1) . '</ele>' : '';
            $trkppts .= "      <trkpt lat=\"{$c[1]}\" lon=\"{$c[0]}\">{$eleXml}</trkpt>\n";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Haus Meli" xmlns="http://www.topografix.com/GPX/1/1">
  <trk>
    <name>{$safeName}</name>
    <trkseg>
{$trkppts}    </trkseg>
  </trk>
</gpx>
XML;
    }

    /**
     * Geplante Gäste-Route → LatLng-Liste für Kartenanzeige.
     *
     * @param  list<array{segmentId: int|string, reversed?: bool}>  $steps
     * @return list<array{0: float, 1: float}>
     */
    public function plannedStepsToLatLngs(array $steps): array
    {
        $ids = [];
        foreach ($steps as $step) {
            $ids[] = (int) ($step['segmentId'] ?? $step['segment_id'] ?? 0);
        }
        $ids = array_values(array_filter($ids));
        $byId = TourSegment::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($steps as $step) {
            $sid = (int) ($step['segmentId'] ?? $step['segment_id'] ?? 0);
            /** @var TourSegment|null $seg */
            $seg = $byId->get($sid);
            if (! $seg) {
                continue;
            }
            $reversed = (bool) ($step['reversed'] ?? false);
            $coords = $seg->coordinates($reversed);
            if ($seg->out_and_back && count($coords) > 1) {
                $back = array_reverse($coords);
                array_shift($back);
                $coords = array_merge($coords, $back);
            }
            foreach ($coords as $c) {
                $lat = (float) $c[1];
                $lng = (float) $c[0];
                if (! is_finite($lat) || ! is_finite($lng)) {
                    continue;
                }
                $out[] = [$lat, $lng];
            }
        }

        return $out;
    }

    /**
     * Geplante Gäste-Route → GPX.
     *
     * @param  list<array{segmentId: int|string, reversed?: bool}>  $steps
     */
    public function combinePlannedStepsToGpx(array $steps, string $name = 'Haus Meli Tour'): string
    {
        $ids = [];
        foreach ($steps as $step) {
            $ids[] = (int) ($step['segmentId'] ?? $step['segment_id'] ?? 0);
        }
        $ids = array_values(array_filter($ids));
        $byId = TourSegment::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $trkppts = '';
        foreach ($steps as $step) {
            $sid = (int) ($step['segmentId'] ?? $step['segment_id'] ?? 0);
            /** @var TourSegment|null $seg */
            $seg = $byId->get($sid);
            if (! $seg) {
                continue;
            }
            $reversed = (bool) ($step['reversed'] ?? false);
            $coords = $seg->coordinates($reversed);
            if ($seg->out_and_back && count($coords) > 1) {
                $back = array_reverse($coords);
                array_shift($back);
                $coords = array_merge($coords, $back);
            }
            foreach ($coords as $c) {
                $lng = $c[0];
                $lat = $c[1];
                $trkppts .= "      <trkpt lat=\"{$lat}\" lon=\"{$lng}\"></trkpt>\n";
            }
        }

        if ($trkppts === '') {
            throw new \InvalidArgumentException('Keine Trackpunkte für die geplante Route.');
        }

        $safeName = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Haus Meli" xmlns="http://www.topografix.com/GPX/1/1">
  <metadata><name>{$safeName}</name></metadata>
  <trk>
    <name>{$safeName}</name>
    <trkseg>
{$trkppts}    </trkseg>
  </trk>
</gpx>
XML;
    }

    /** @param  Collection<int, TourSegment>  $segments */
    public function estimateDurationMinutes(Collection $segments): int
    {
        $minutes = 0;
        foreach ($segments as $seg) {
            $isCable = (bool) ($seg->is_cable_car ?? false)
                || in_array('seilbahn', $seg->tags ?? [], true)
                || in_array('cable_car', $seg->tags ?? [], true);
            $minutes += TourDurationEstimator::resolveMinutes(
                $seg->duration_min !== null ? (int) $seg->duration_min : null,
                (string) ($seg->mode ?: 'hike'),
                (float) ($seg->distance_km ?? 0),
                (int) ($seg->elevation_m ?? 0),
                $isCable
            );
        }

        return max(1, $minutes);
    }

    private function haversineM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dP = deg2rad($lat2 - $lat1);
        $dL = deg2rad($lng2 - $lng1);
        $a = sin($dP / 2) ** 2 + cos($p1) * cos($p2) * sin($dL / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
