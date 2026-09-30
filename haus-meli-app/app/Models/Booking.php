<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    // Da wir auf dem gleichen Server sind, brauchen wir KEINE extra Connection!
    // Wir sagen Laravel einfach: "Geh in die andere DB und nimm dort die Tabelle bookings"
    
    protected $table = 'ferienwohnung_laravel.bookings'; 
}