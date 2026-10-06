<?php

return [
    // Haus-Punkt. Die Leiste oben bleibt immer hier, auch wenn die Wetterseite woanders steht.
    'place' => env('WEATHER_PLACE', 'Lauterach'),
    'lat' => (float) env('WEATHER_LAT', 47.477),
    'lon' => (float) env('WEATHER_LON', 9.729),
    'asl' => (int) env('WEATHER_ASL', 412),
    'timezone' => env('WEATHER_TZ', 'Europe/Vienna'),
    // Ein Abruf pro Ort, gültig bis zur nächsten vollen Stunde.
    'api_key' => env('METEOBLUE_API_KEY', ''),
    // Meteoblue kennt keine Ortsnamen, nur einen Punkt (Breite, Länge, Höhe).
    // Tal ist der Ort, Berg ein Punkt auf der Piste. Gibt es nur einen Punkt, entfällt die Wahl.
    'orts' => [
        'lauterach' => [
            'label' => env('WEATHER_PLACE', 'Lauterach'),
            'zones' => [
                'home' => [
                    'lat' => (float) env('WEATHER_LAT', 47.477),
                    'lon' => (float) env('WEATHER_LON', 9.729),
                    'asl' => (int) env('WEATHER_ASL', 412),
                ],
            ],
        ],
        'kappl' => [
            'label' => 'Kappl',
            'zones' => [
                'tal' => ['lat' => 47.0667, 'lon' => 10.3667, 'asl' => 1258],
                'berg' => ['lat' => 47.0688, 'lon' => 10.3563, 'asl' => 1900],
            ],
        ],
        'ischgl' => [
            'label' => 'Ischgl',
            'zones' => [
                'berg' => ['lat' => 46.9818, 'lon' => 10.3176, 'asl' => 2319],
            ],
        ],
        'see' => [
            'label' => 'See',
            'zones' => [
                'berg' => ['lat' => 47.0734, 'lon' => 10.4767, 'asl' => 1802],
            ],
        ],
    ],
];
