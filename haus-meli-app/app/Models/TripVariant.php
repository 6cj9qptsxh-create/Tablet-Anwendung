<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TripVariant extends Model
{
    protected $fillable = [
        'trip_id', 'name', 'is_default', 'sort_order',
        'distance_km', 'elevation_m', 'duration_min',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'distance_km' => 'float',
        'elevation_m' => 'integer',
        'duration_min' => 'integer',
        'sort_order' => 'integer',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(TourSegment::class, 'trip_variant_segment')
            ->withPivot(['position', 'reversed'])
            ->orderByPivot('position');
    }

    public function recalculateStats(): void
    {
        $this->loadMissing('segments');

        $dist = 0.0;
        $elev = 0;
        $duration = 0;
        $hasDuration = false;

        foreach ($this->segments as $seg) {
            $dist += (float) $seg->distance_km;
            $elev += (int) $seg->elevation_m;
            if ($seg->duration_min !== null) {
                $duration += (int) $seg->duration_min;
                $hasDuration = true;
            }
        }

        $this->forceFill([
            'distance_km' => round($dist, 2),
            'elevation_m' => $elev,
            'duration_min' => $hasDuration ? $duration : null,
        ])->save();
    }

    public function googleMapsUrl(): ?string
    {
        $this->loadMissing('segments.fromNode', 'segments.toNode');

        $coords = [];
        foreach ($this->segments as $seg) {
            $reversed = (bool) ($seg->pivot->reversed ?? false);
            $line = $seg->coordinates($reversed);
            foreach ($line as $c) {
                if (isset($c[0], $c[1])) {
                    $coords[] = [$c[1], $c[0]]; // lat,lng
                }
            }
        }

        if (count($coords) < 2) {
            return null;
        }

        $start = $coords[0];
        $end = $coords[array_key_last($coords)];

        // Google Maps Directions: Start → Ziel (Zwischenpunkte optional gekürzt)
        $waypoints = [];
        $step = max(1, (int) floor(count($coords) / 8));
        for ($i = $step; $i < count($coords) - 1; $i += $step) {
            $waypoints[] = $coords[$i][0] . ',' . $coords[$i][1];
            if (count($waypoints) >= 8) {
                break;
            }
        }

        $url = 'https://www.google.com/maps/dir/?api=1'
            . '&origin=' . urlencode($start[0] . ',' . $start[1])
            . '&destination=' . urlencode($end[0] . ',' . $end[1])
            . '&travelmode=walking';

        if ($waypoints !== []) {
            $url .= '&waypoints=' . urlencode(implode('|', $waypoints));
        }

        return $url;
    }
}
