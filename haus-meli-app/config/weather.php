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
    // Skigebiete sind je ein Punkt auf der Piste, nicht der ganze Berg.
    'places' => [
        'lauterach' => [
            'label' => env('WEATHER_PLACE', 'Lauterach'),
            'lat' => (float) env('WEATHER_LAT', 47.477),
            'lon' => (float) env('WEATHER_LON', 9.729),
            'asl' => (int) env('WEATHER_ASL', 412),
        ],
        'kappl' => [
            'label' => 'Kappl',
            'lat' => 47.0667,
            'lon' => 10.3667,
            'asl' => 1258,
        ],
        'kappl-ski' => [
            'label' => 'Kappl Skigebiet',
            'lat' => 47.0688,
            'lon' => 10.3563,
            'asl' => 1900,
        ],
        'ischgl-ski' => [
            'label' => 'Ischgl Skigebiet',
            'lat' => 46.9818,
            'lon' => 10.3176,
            'asl' => 2319,
        ],
        'see-ski' => [
            'label' => 'See Skigebiet',
            'lat' => 47.0734,
            'lon' => 10.4767,
            'asl' => 1802,
        ],
    ],
];
