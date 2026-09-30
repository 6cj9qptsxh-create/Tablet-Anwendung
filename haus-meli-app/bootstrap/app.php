<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trusted = env('TRUSTED_PROXIES');
        if (is_string($trusted) && $trusted !== '') {
            $middleware->trustProxies(
                at: $trusted === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $trusted)))),
            );
        }

        $middleware->validateCsrfTokens(except: [
            'tours/planned/share',
            'tours/elevation',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
