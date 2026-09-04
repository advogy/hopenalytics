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
        // Appended (runs after the framework's own web-group middleware, including
        // StartSession) — SetLocale reads/writes session('locale'), which needs a started
        // session to mean anything, so it can't run any earlier than this. A route-model-binding
        // 404 (e.g. a stale link to a since-deleted church) can still throw before this ever
        // runs, in which case $currentBrand never gets shared — handled instead by
        // brand-mark.blade.php's own defensive fallback (see that file), rather than by
        // reordering this middleware, since SetLocale can't safely move any earlier.
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\ResolveBrand::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // CI calls /deploy/run directly (no session, so no CSRF token to send) — the route's
        // own token check (VerifyDeployToken) is what actually guards it.
        $middleware->validateCsrfTokens(except: [
            'deploy/run',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A stale CSRF token (session/tab left open past SESSION_LIFETIME) throws
        // TokenMismatchException on the next form submit. Laravel's own prepareException()
        // converts that to a plain 419 HttpException before any render() callback keyed on
        // TokenMismatchException would ever see it, so this has to hook the final response
        // instead — redirecting back reloads the form with a fresh token rather than dumping
        // the user on Laravel's bare "419 | Page Expired" page.
        $exceptions->respond(function ($response, $e, $request) {
            if ($response->getStatusCode() === 419 && ! $request->expectsJson()) {
                return redirect()->back()->with('error', __('auth.session_expired'));
            }

            return $response;
        });
    })->create();
