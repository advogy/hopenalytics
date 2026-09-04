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
        // ResolveBrand/SetLocale specifically prepended (run BEFORE the framework's own web-group
        // middleware, including SubstituteBindings) rather than appended — a route-model-binding
        // failure (e.g. a stale link to a since-deleted church) throws its 404 during
        // SubstituteBindings, and if that happens before ResolveBrand ever runs, $currentBrand is
        // never shared and the 404 error page's own layout (which renders <x-brand-mark>) 500s
        // instead of showing the actual 404 — confirmed live in production. See also
        // brand-mark.blade.php's own defensive fallback for the same reason, in case some other
        // path still manages to skip this middleware entirely.
        $middleware->web(prepend: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\ResolveBrand::class,
        ]);
        $middleware->web(append: [
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
