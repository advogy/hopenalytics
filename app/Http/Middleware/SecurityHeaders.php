<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Nothing in the app frames itself or gets framed by anyone else, so denying it outright
     * is the safe default — the two headers below are the modern (CSP) and legacy
     * (X-Frame-Options) ways of saying the same thing, kept together for older browsers that
     * don't understand frame-ancestors. Scoped to just that directive rather than a full CSP:
     * this app's views rely on plenty of inline <script> blocks (searchable-select wiring,
     * etc.), so a script-src-restricting CSP would need a nonce rollout across every view to
     * avoid breaking the app — out of scope for this pass.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
