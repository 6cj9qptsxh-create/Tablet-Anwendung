<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourSegmentImage extends Model
{
    protected $fillable = [
        'tour_segment_id', 'path', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(TourSegment::class, 'tour_segment_id');
    }
}
