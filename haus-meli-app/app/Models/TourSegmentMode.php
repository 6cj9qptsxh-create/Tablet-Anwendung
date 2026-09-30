<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourSegmentMode extends Model
{
    protected $fillable = [
        'tour_segment_id', 'mode', 'duration_min', 'difficulty', 'fitness',
        'tags', 'description', 'is_active',
    ];

    protected $casts = [
        'duration_min' => 'integer',
        'difficulty' => 'integer',
        'fitness' => 'integer',
        'tags' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(TourSegment::class, 'tour_segment_id');
    }

    public function localizedDescription(string $lang = 'de'): string
    {
        $d = $this->description;
        if (! is_array($d)) {
            return (string) ($d ?? '');
        }

        return (string) ($d[$lang] ?? $d['de'] ?? '');
    }
}
