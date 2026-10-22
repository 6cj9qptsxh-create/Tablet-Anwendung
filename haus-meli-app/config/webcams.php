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
        'tal' => [
            'name' => 'Valley',
            'place' => 'See',
            'asl' => 1100,
            'page' => 'https://www.kappl-see.com/de/live-infos/webcams/webcam-valley_webcam_104131847',
            'image' => 'https://www.kappl-see.com/var/webcams/104131847.jpg',
        ],
        'berg' => [
            'name' => 'Nordseite',
            'place' => 'See',
            'asl' => 2160,
            'page' => 'https://www.kappl-see.com/de/live-infos/webcams/webcam-nordseite_webcam_104131843',
            'image' => 'https://www.kappl-see.com/var/webcams/104131843.jpg',
        ],
    ],
    'ischgl' => [
        'tal' => [
            'name' => 'Ischgl Dorf',
            'place' => 'Ischgl',
            'asl' => 1400,
            'page' => 'https://www.ischgl.com/de/reise-planen/webcams/ischgl-dorf_webcam_124696581',
            'image' => 'https://www.ischgl.com/var/webcams/124696581.jpg',
        ],
        'berg' => [
            'name' => 'Idalp',
            'place' => 'Ischgl',
            'asl' => 2320,
            'page' => 'https://www.ischgl.com/de/reise-planen/webcams/idalp_webcam_100178345',
            'image' => 'https://www.ischgl.com/var/webcams/100178345.jpg',
        ],
    ],
    'galtuer' => [
        'tal' => [
            'name' => 'Dorf',
            'place' => 'Galtür',
            'asl' => 1600,
            'page' => 'https://www.galtuer.com/de/reise-planen/webcams/webcam-dorf_webcam_104131863',
            'image' => 'https://www.galtuer.com/var/webcams/104131863.jpg',
        ],
        'berg' => [
            'name' => 'Breitspitzbahn',
            'place' => 'Galtür',
            'asl' => 2120,
            'page' => 'https://www.galtuer.com/de/reise-planen/webcams/webcam-breitspitzbahn_webcam_104131861',
            'image' => 'https://www.galtuer.com/var/webcams/104131861.jpg',
        ],
    ],
];
