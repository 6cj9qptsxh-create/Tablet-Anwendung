<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    protected $fillable = [
        'title', 'description', 'category', 'season', 'dist', 'alt',
        'difficulty', 'fitness', 'tags', 'images', 'video', 'komoot_url',
        'is_active', 'legacy_tour_id',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'tags' => 'array',
        'images' => 'array',
        'dist' => 'float',
        'alt' => 'integer',
        'difficulty' => 'integer',
        'fitness' => 'integer',
        'is_active' => 'boolean',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(TripVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function defaultVariant(): ?TripVariant
    {
        return $this->variants->firstWhere('is_default', true) ?? $this->variants->first();
    }

    /** Stats für Filter/Karten: Default-Variante oder Trip-Fallback */
    public function displayDist(): float
    {
        $v = $this->relationLoaded('variants') ? $this->defaultVariant() : null;

        return $v && (float) $v->distance_km > 0 ? (float) $v->distance_km : (float) $this->dist;
    }

    public function displayAlt(): int
    {
        $v = $this->relationLoaded('variants') ? $this->defaultVariant() : null;

        return $v && (int) $v->elevation_m > 0 ? (int) $v->elevation_m : (int) $this->alt;
    }

    public function localized(string $field, string $lang = 'de'): string
    {
        $val = $this->{$field};
        if (is_string($val)) {
            return $val;
        }
        if (! is_array($val)) {
            return '';
        }

        return (string) ($val[$lang] ?? $val['de'] ?? reset($val) ?: '');
    }

    /** Payload für Gast-Frontend (kompatibel zu tours.js + Map) */
    public function toGuestArray(): array
    {
        $variants = $this->variants->map(function (TripVariant $variant) {
            $segments = $variant->segments->map(function (TourSegment $seg) {
                $reversed = (bool) ($seg->pivot->reversed ?? false);

                return [
                    'id' => $seg->id,
                    'name' => $seg->name,
                    'mode' => $seg->mode,
                    'distance_km' => (float) $seg->distance_km,
                    'elevation_m' => (int) $seg->elevation_m,
                    'reversed' => $reversed,
                    'geojson' => [
                        'type' => 'LineString',
                        'coordinates' => $seg->coordinates($reversed),
                    ],
                ];
            })->values()->all();

            $start = null;
            $end = null;
            foreach ($segments as $seg) {
                $coords = $seg['geojson']['coordinates'] ?? [];
                if ($coords === []) {
                    continue;
                }
                if ($start === null) {
                    $start = ['lng' => $coords[0][0], 'lat' => $coords[0][1]];
                }
                $last = $coords[array_key_last($coords)];
                $end = ['lng' => $last[0], 'lat' => $last[1]];
            }

            return [
                'id' => $variant->id,
                'name' => $variant->name,
                'is_default' => (bool) $variant->is_default,
                'dist' => (float) $variant->distance_km,
                'alt' => (int) $variant->elevation_m,
                'duration_min' => $variant->duration_min,
                'segments' => $segments,
                'start' => $start,
                'end' => $end,
                'has_map' => collect($segments)->contains(fn ($s) => count($s['geojson']['coordinates'] ?? []) >= 2),
                'gpx_url' => route('trips.variant.gpx', $variant),
                'maps_url' => $variant->googleMapsUrl(),
            ];
        })->values()->all();

        $default = collect($variants)->firstWhere('is_default', true) ?? ($variants[0] ?? null);

        return [
            'id' => $this->id,
            'title' => is_array($this->title) ? $this->title : ['de' => (string) $this->title],
            'description' => is_array($this->description)
                ? $this->description
                : ['de' => (string) ($this->description ?? '')],
            'category' => $this->category,
            'dist' => $default['dist'] ?? $this->displayDist(),
            'alt' => $default['alt'] ?? $this->displayAlt(),
            'difficulty' => (int) $this->difficulty,
            'fitness' => (int) $this->fitness,
            'tags' => $this->tags ?? [],
            'images' => $this->images ?? [],
            'video' => $this->video,
            'komoot_url' => $this->komoot_url,
            'has_map' => (bool) ($default['has_map'] ?? false),
            'variants' => $variants,
        ];
    }
}
