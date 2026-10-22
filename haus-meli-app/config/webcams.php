<?php

// Standbilder ohne Abspielsymbol. Videokameras von Kappl/See legen ein Play-Zeichen
// ins Bild, obwohl der Klick nur die Kameraseite öffnet. Die bleiben draussen.
// ort und zone entsprechen den Wetterpunkten in config/weather.php.

return [
    'kappl' => [
        'tal' => [
            'name' => 'Diasbahn Talstation',
            'place' => 'Kappl',
            'asl' => 1180,
            'page' => 'https://www.kappl-see.com/de/live-infos/webcams/webcam-diasbahn-talstation_webcam_104131839',
            'image' => 'https://www.kappl-see.com/var/webcams/104131839.jpg',
        ],
        'berg' => [
            'name' => 'Diasbahn Bergstation',
            'place' => 'Kappl',
            'asl' => 1830,
            'page' => 'https://www.kappl-see.com/de/live-infos/webcams/webcam-diasbahn-bergstation_webcam_104131835',
            'image' => 'https://www.kappl-see.com/var/webcams/104131835.jpg',
        ],
    ],
    'see' => [
        'berg' => [
            'name' => 'Nordseite',
            'place' => 'See',
            'asl' => 2160,
            'page' => 'https://www.kappl-see.com/de/live-infos/webcams/webcam-nordseite_webcam_104131843',
            'image' => 'https://www.kappl-see.com/var/webcams/104131843.jpg',
        ],
    ],
    'ischgl' => [
        'berg' => [
            'name' => 'Idalp',
            'place' => 'Ischgl',
            'asl' => 2320,
            'page' => 'https://www.ischgl.com/de/reise-planen/webcams/idalp_webcam_100178345',
            'image' => 'https://www.ischgl.com/var/webcams/100178345.jpg',
        ],
    ],
];
