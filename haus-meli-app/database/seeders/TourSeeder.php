<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tour;

class TourSeeder extends Seeder
{
    public function run(): void
    {
        // Löscht vorherige Testdaten (optional, falls du ihn mehrmals ausführst)
        Tour::truncate();

        $tours = [
            // --- WANDERN ---
            [
                'title' => 'Panoramaweg zum Hausberg',
                'description' => 'Eine gemütliche Wanderung mit tollem Ausblick über das ganze Tal. Ideal für den Nachmittag.',
                'category' => 'hiking',
                'dist' => 4.5,
                'alt' => 150,
                'difficulty' => 1,
                'fitness' => 1,
                'tags' => ['panorama', 'family'],
                'images' => ['https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=600&q=80'],
                'is_active' => true,
            ],
            [
                'title' => 'Gipfelstürmer Route',
                'description' => 'Anspruchsvolle Tagestour für erfahrene Wanderer. Trittsicherheit erforderlich!',
                'category' => 'hiking',
                'dist' => 14.2,
                'alt' => 1150,
                'difficulty' => 3,
                'fitness' => 3,
                'tags' => ['alpine', 'expert'],
                'images' => ['https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=600&q=80'],
                'is_active' => true,
            ],

            // --- BIKE & HIKE ---
            [
                'title' => 'Silvretta E-Bike Trail',
                'description' => 'Wunderschöne Strecke entlang des Flusses. Perfekt mit dem E-Bike machbar.',
                'category' => 'biking',
                'dist' => 28.5,
                'alt' => 450,
                'difficulty' => 2,
                'fitness' => 2,
                'tags' => ['ebike', 'nature'],
                'images' => ['https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=600&q=80'],
                'is_active' => true,
            ],
            [
                'title' => 'Freeride Downhill Action',
                'description' => 'Adrenalin pur! Mit der Bergbahn hoch und über steinige Trails wieder runter.',
                'category' => 'biking',
                'dist' => 6.0,
                'alt' => 1200, // Zählt in dem Fall meist als Tiefenmeter, aber für den Slider nehmen wir alt
                'difficulty' => 3,
                'fitness' => 2,
                'tags' => ['action', 'downhill'],
                'images' => ['https://images.unsplash.com/photo-1544191696-102dbdaeeaa0?auto=format&fit=crop&w=600&q=80'],
                'is_active' => true,
            ],

            // --- LANGLAUF ---
            [
                'title' => 'Sonnen-Loipe im Tal',
                'description' => 'Flache Loipe, perfekt präpariert. Ideal für Anfänger.',
                'category' => 'cross_country',
                'dist' => 8.0,
                'alt' => 30,
                'difficulty' => 1,
                'fitness' => 1,
                'tags' => ['classic', 'skating'],
                'images' => ['https://images.unsplash.com/photo-1544185303-3a52bb96b997?auto=format&fit=crop&w=600&q=80'],
                'is_active' => true,
            ],
            [
                'title' => 'Höhen-Loipe Waldgrenze',
                'description' => 'Schneesichere Loipe auf 1.800m Höhe mit knackigen Anstiegen.',
                'category' => 'cross_country',
                'dist' => 15.5,
                'alt' => 350,
                'difficulty' => 2,
                'fitness' => 3,
                'tags' => ['skating', 'panorama'],
                'images' => ['https://images.unsplash.com/photo-1610992015732-2849b7634612?auto=format&fit=crop&w=600&q=80'],
                'is_active' => true,
            ],

            // --- RODELN ---
            [
                'title' => 'Familien-Rodelbahn',
                'description' => 'Gemütlicher Aufstieg und lustige Abfahrt. Abends beleuchtet!',
                'category' => 'sledding',
                'dist' => 3.5,
                'alt' => 400,
                'difficulty' => 1,
                'fitness' => 1,
                'tags' => ['family', 'night'],
                'images' => ['https://images.unsplash.com/photo-1484313544071-4d67c88b99be?auto=format&fit=crop&w=600&q=80'],
                'is_active' => true,
            ]
        ];

        // Wir nutzen Model::create, damit Laravel die JSON-Arrays sauber umwandelt
        foreach ($tours as $tour) {
            Tour::create($tour);
        }
    }
}