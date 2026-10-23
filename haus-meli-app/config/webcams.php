<?php

// Standbild plus, wo Feratel eines ablegt, nur das neueste Video.
// Der Verlauf auf der Kameraseite bleibt draussen. ort und zone entsprechen config/weather.php.

return [
    'kappl' => [
        'tal' => [
            'name' => 'Diasbahn Talstation',
            'place' => 'Kappl',
            'asl' => 1180,
            'page' => 'https://www.kappl-see.com/de/live-infos/webcams/webcam-diasbahn-talstation_webcam_104131839',
            'image' => 'https://www.kappl-see.com/var/webcams/104131839.jpg',
            'feratel' => 5589,
        ],
        'berg' => [
            'name' => 'Diasbahn Bergstation',
            'place' => 'Kappl',
            'asl' => 1830,
            'page' => 'https://www.kappl-see.com/de/live-infos/webcams/webcam-diasbahn-bergstation_webcam_104131835',
            'image' => 'https://www.kappl-see.com/var/webcams/104131835.jpg',
            'feratel' => 5585,
        ],
    ],
    'see' => [
        'tal' => [
            'name' => 'See Paznaun Tal',
            'place' => 'See',
            'asl' => 1100,
            'page' => 'https://www.kappl-see.com/de/live-infos/webcams',
            'image' => 'https://www.kappl-see.com/var/webcams/104131847.jpg',
        ],
        'medrig' => [
            'name' => 'Mittelstation See',
            'place' => 'See',
            'asl' => 1810,
            'page' => 'https://www.kappl-see.com/de/live-infos/webcams/webcam-medrig-alm_webcam_104131857',
            'image' => 'https://www.kappl-see.com/var/webcams/104131857.jpg',
            'feratel' => 5558,
        ],
        'furglerblick' => [
            'name' => 'Furglerblick',
            'place' => 'See',
            'asl' => 2570,
            'page' => 'https://www.kappl-see.com/de/live-infos/webcams/webcam-furglerblick_webcam_104131855',
            'image' => 'https://www.kappl-see.com/var/webcams/104131855.jpg',
            'feratel' => 5557,
        ],
    ],
    'ischgl' => [
        'tal' => [
            'name' => 'Ischgl Dorf',
            'place' => 'Ischgl',
            'asl' => 1400,
            'page' => 'https://www.ischgl.com/de/reise-planen/webcams/ischgl-dorf_webcam_124696581',
            'image' => 'https://www.ischgl.com/var/webcams/124696581.jpg',
            'archive' => '/var/webcams/archive/124696581-paznaun',
        ],
        'berg' => [
            'name' => 'Idalp',
            'place' => 'Ischgl',
            'asl' => 2320,
            'page' => 'https://www.ischgl.com/de/reise-planen/webcams/idalp_webcam_100178345',
            'image' => 'https://www.ischgl.com/var/webcams/100178345.jpg',
            'archive' => '/var/webcams/archive/99396-idalp',
        ],
    ],
    'galtuer' => [
        'tal' => [
            'name' => 'Dorf',
            'place' => 'Galtür',
            'asl' => 1600,
            'page' => 'https://www.galtuer.com/de/reise-planen/webcams/webcam-dorf_webcam_104131863',
            'image' => 'https://www.galtuer.com/var/webcams/104131863.jpg',
            'feratel' => 5548,
        ],
        'berg' => [
            'name' => 'Breitspitzbahn',
            'place' => 'Galtür',
            'asl' => 2120,
            'page' => 'https://www.galtuer.com/de/reise-planen/webcams/webcam-breitspitzbahn_webcam_104131861',
            'image' => 'https://www.galtuer.com/var/webcams/104131861.jpg',
            'feratel' => 5547,
        ],
    ],
];
