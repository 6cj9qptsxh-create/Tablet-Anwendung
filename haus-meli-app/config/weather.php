<?php

return [
    // Aktuell Lauterach. Kappl kommt später über dieselben Variablen.
    'place' => env('WEATHER_PLACE', 'Lauterach'),
    'lat' => (float) env('WEATHER_LAT', 47.477),
    'lon' => (float) env('WEATHER_LON', 9.729),
    'asl' => (int) env('WEATHER_ASL', 412),
    'timezone' => env('WEATHER_TZ', 'Europe/Vienna'),
    // Ein gemeinsamer Abruf, gültig bis zur nächsten vollen Stunde.
    'api_key' => env('METEOBLUE_API_KEY', ''),
];
