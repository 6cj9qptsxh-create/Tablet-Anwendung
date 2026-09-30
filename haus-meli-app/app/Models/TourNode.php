<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourNode extends Model
{
    protected $fillable = [
        'name', 'lat', 'lng', 'image_path', 'notes', 'description',
        'is_start_candidate', 'start_kinds', 'is_highlight', 'is_dead_end',
        'offers_food', 'offers_lodging', 'season_open',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'is_start_candidate' => 'boolean',
        'start_kinds' => 'array',
        'is_highlight' => 'boolean',
        'is_dead_end' => 'boolean',
        'offers_food' => 'boolean',
        'offers_lodging' => 'boolean',
        'description' => 'array',
    ];

    public const START_KINDS = [
        'ferienwohnung' => 'Ferienwohnung',
        'bus' => 'Bus',
        'auto' => 'Auto',
    ];

    public function segmentsFrom(): HasMany
    {
        return $this->hasMany(TourSegment::class, 'from_node_id');
    }

    public function segmentsTo(): HasMany
    {
        return $this->hasMany(TourSegment::class, 'to_node_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(TourNodeImage::class, 'tour_node_id')->orderBy('sort_order');
    }

    public function isHut(): bool
    {
        return (bool) $this->offers_food || (bool) $this->offers_lodging;
    }

    public function toGuestArray(): array
    {
        $kinds = is_array($this->start_kinds) ? array_values($this->start_kinds) : [];
        $images = $this->relationLoaded('images')
            ? $this->images
            : $this->images()->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'is_start_candidate' => (bool) $this->is_start_candidate || $kinds !== [],
            'start_kinds' => $kinds,
            'is_highlight' => (bool) $this->is_highlight,
            'is_dead_end' => (bool) ($this->is_dead_end ?? false),
            'is_hut' => $this->isHut(),
            'offers_food' => (bool) $this->offers_food,
            'offers_lodging' => (bool) $this->offers_lodging,
            'season_open' => $this->season_open,
            'notes' => $this->notes,
            'description' => is_array($this->description)
                ? [
                    'de' => (string) ($this->description['de'] ?? ''),
                    'en' => (string) ($this->description['en'] ?? ''),
                ]
                : ['de' => (string) ($this->description ?? ''), 'en' => ''],
            'images' => $images->pluck('path')->values()->all(),
        ];
    }
}
