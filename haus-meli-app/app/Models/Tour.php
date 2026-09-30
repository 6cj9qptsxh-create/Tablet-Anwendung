<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tour extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'category', 'season', 'dist', 
        'alt', 'difficulty', 'fitness', 'tags', 'images', 'video', 'is_active'
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'tags' => 'array',
        'images' => 'array',
        'dist' => 'float',
        'is_active' => 'boolean',
    ];
}