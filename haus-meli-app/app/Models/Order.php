<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'delivery_date' => 'date',
        'items' => 'array',
        'total' => 'decimal:2',
        'placed_at' => 'datetime',
    ];

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
