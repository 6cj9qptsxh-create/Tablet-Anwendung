<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourSegment extends Model
{
    protected $fillable = [
        'name', 'from_node_id', 'to_node_id', 'mode', 'bidirectional',
        'distance_km', 'elevation_m', 'duration_min', 'geojson', 'gpx_path',
        'tags', 'notes', 'is_highlight', 'out_and_back',
    ];

    protected $casts = [
        'bidirectional' => 'boolean',
        'is_highlight' => 'boolean',
        'out_and_back' => 'boolean',
        'distance_km' => 'float',
        'elevation_m' => 'integer',
        'duration_min' => 'integer',
        'geojson' => 'array',
        'tags' => 'array',
    ];

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(TourNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(TourNode::class, 'to_node_id');
    }

    public function modeProfiles(): HasMany
    {
        return $this->hasMany(TourSegmentMode::class, 'tour_segment_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(TourSegmentImage::class, 'tour_segment_id')->orderBy('sort_order');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(TripVariant::class, 'trip_variant_segment')
            ->withPivot(['position', 'reversed'])
            ->orderByPivot('position');
    }

    public function profileFor(string $mode): ?TourSegmentMode
    {
        return $this->modeProfiles->firstWhere('mode', $mode);
    }

    public function activeModes(): array
    {
        return $this->modeProfiles
            ->where('is_active', true)
            ->pluck('mode')
            ->values()
            ->all();
    }

    /** Koordinatenliste [[lng,lat], ...] ggf. umgekehrt */
    public function coordinates(bool $reversed = false): array
    {
        $coords = $this->geojson['coordinates'] ?? [];
        if (! is_array($coords) || $coords === []) {
            return [];
        }

        return $reversed ? array_reverse($coords) : $coords;
    }

    public function toGuestArray(): array
    {
        $modes = $this->relationLoaded('modeProfiles')
            ? $this->modeProfiles
            : $this->modeProfiles()->get();

        $images = $this->relationLoaded('images')
            ? $this->images
            : $this->images()->get();

        $tags = is_array($this->tags) ? array_values($this->tags) : [];
        foreach ($modes as $m) {
            foreach (($m->tags ?? []) as $t) {
                if (! in_array($t, $tags, true)) {
                    $tags[] = $t;
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'from_node_id' => (int) $this->from_node_id,
            'to_node_id' => (int) $this->to_node_id,
            'distance_km' => (float) $this->distance_km,
            'elevation_m' => (int) $this->elevation_m,
            'bidirectional' => false,
            // Highlight sitzt am Knoten; Segment-Flag nur noch abgeleitet für Sortierung/Badge
            'is_highlight' => (bool) (
                ($this->relationLoaded('fromNode') && $this->fromNode?->is_highlight)
                || ($this->relationLoaded('toNode') && $this->toNode?->is_highlight)
            ),
            'out_and_back' => (bool) $this->out_and_back,
            'tags' => $tags,
            'is_cable_car' => in_array('seilbahn', $tags, true) || in_array('cable_car', $tags, true),
            'geojson' => [
                'type' => 'LineString',
                'coordinates' => $this->coordinates(false),
            ],
            'images' => $images->pluck('path')->values()->all(),
            'modes' => $modes->map(fn (TourSegmentMode $m) => [
                'mode' => $m->mode,
                'duration_min' => $m->duration_min,
                'difficulty' => (int) $m->difficulty,
                'fitness' => (int) $m->fitness,
                'tags' => $m->tags ?? [],
                'description' => is_array($m->description)
                    ? $m->description
                    : ['de' => (string) ($m->description ?? ''), 'en' => ''],
                'is_active' => (bool) $m->is_active,
            ])->values()->all(),
        ];
    }
}
