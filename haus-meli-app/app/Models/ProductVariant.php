<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    // Erlaubt das automatische Speichern aller Felder beim Import
    protected $guarded = [];

    // Die Beziehung: Eine Variante GEHÖRT ZU EINEM Produkt
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}