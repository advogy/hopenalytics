<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to every "add a new Divisi/Uni/Daerah/Institusi/Gereja/Personal" (and its matching
 * edit) route, per the user's explicit call to stop the browser's Back button from
 * creating duplicate data. Without this, pressing Back right after a successful submit
 * doesn't re-request the page from the server — the browser's cache (bfcache or otherwise)
 * silently redisplays the exact create-form page as it looked right before that POST, with
 * no visible sign anything was saved. Someone who doesn't realize that and submits again
 * ends up with a second, near-identical record.
 *
 * These headers force every GET back to this page (Back/Forward included) to be a genuine
 * fresh request to the server instead — landing on a real, blank create form rather than a
 * cached, easy-to-resubmit one. It doesn't by itself stop a genuine repeat submission (the
 * form is still right there, still submittable) — entity-crud-form.blade.php's own
 * data-disable-on-submit guards against a double-click/double-submit, and each entity's
 * existing x-similar-name-check advisory (NameSimilarity) is what actually warns before a
 * true duplicate-name save.
 */
class NoCacheFormPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
