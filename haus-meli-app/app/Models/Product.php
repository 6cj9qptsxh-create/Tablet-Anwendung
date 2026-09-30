<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    // Erlaubt das automatische Speichern aller Felder beim Import
    protected $guarded = [];

    // Die Beziehung: Ein Produkt hat VIELE Varianten
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}