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
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // CI calls /deploy/run directly (no session, so no CSRF token to send) — the route's
        // own token check (VerifyDeployToken) is what actually guards it.
        $middleware->validateCsrfTokens(except: [
            'deploy/run',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
