<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF-Ablage (NAS)
    |--------------------------------------------------------------------------
    */
    'pdf_root' => env('ORDER_PDF_ROOT', '\\\\NAS\\web\\ferienwohnung\\bestellungen'),

    /*
    |--------------------------------------------------------------------------
    | Kopfdaten auf dem Bestell-PDF
    |--------------------------------------------------------------------------
    */
    'address' => env('ORDER_PDF_ADDRESS', ''),

    'names' => [
        'guest' => env('ORDER_PDF_NAME_GUEST', 'Ferienwohnung'),
        'owner' => env('ORDER_PDF_NAME_OWNER', 'Fam. Jörg'),
    ],

    'filename_slugs' => [
        'guest' => 'ferienwohnung',
        'owner' => 'fam-joerg',
    ],

];
