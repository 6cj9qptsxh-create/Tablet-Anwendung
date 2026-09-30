<?php

/**
 * Router für PHP's Built-in Server:
 *   php -S 0.0.0.0:8000 -t public public/router.php
 *
 * Ohne diese Datei liefern nur echte Dateien (z. B. /) eine Antwort —
 * Laravel-Routen wie /tours/planned/share enden sonst in HTTP 404.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

if ($uri !== '/' && file_exists(__DIR__.$uri) && ! is_dir(__DIR__.$uri)) {
    return false;
}

require_once __DIR__.'/index.php';
