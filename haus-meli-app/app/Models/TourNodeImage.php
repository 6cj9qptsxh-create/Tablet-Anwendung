<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourNodeImage extends Model
{
    protected $fillable = [
        'tour_node_id', 'path', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(TourNode::class, 'tour_node_id');
    }
}
