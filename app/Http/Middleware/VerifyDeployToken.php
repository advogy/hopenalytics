<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /deploy/run endpoint (CI's zip-extract + migrate trigger, since Hostinger shared
 * hosting has no SSH). hash_equals() for timing-safe comparison; if services.deploy.token isn't
 * configured at all, the route refuses every request rather than silently allowing one — an
 * unset token must never mean "open".
 */
class VerifyDeployToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.deploy.token');
        $given = $request->header('X-Deploy-Token', '');

        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, $given)) {
            abort(403);
        }

        return $next($request);
    }
}
