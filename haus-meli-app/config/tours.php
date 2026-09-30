<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tour-Admin Zugang
    |--------------------------------------------------------------------------
    | Owner-IP (wie Shop) ODER Passwort-Login (Session).
    */
    'admin_password' => env('TOURS_ADMIN_PASSWORD', ''),

    'modes' => [
        'hike' => 'Wandern',
        'bike' => 'Rad',
        'ebike' => 'E-Bike',
        'sled' => 'Rodeln',
        'ski' => 'Ski / Langlauf',
    ],

    'profile_modes' => [
        'hike' => 'Wandern',
        'bike' => 'Rad',
        'ebike' => 'E-Bike',
    ],

    'categories' => [
        'hiking' => 'Wandern',
        'bike' => 'Rad',
        'biking' => 'Bike & Hike (Wandern + Rad)',
        'cross_country' => 'Langlauf',
        'sledding' => 'Rodeln',
    ],
];
