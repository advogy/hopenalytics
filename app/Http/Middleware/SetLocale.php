<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale');

        if (! in_array($locale, ['id', 'en'], true)) {
            // First visit — no explicit choice stored yet (see LocaleController, which
            // always wins once a visitor actually uses the language switcher). Default to
            // whatever the browser's own language is set to, per the user's explicit call
            // ("kalau di Indonesia, pilih bahasa Indonesia, selain itu EN") — approximated
            // via Accept-Language rather than real IP geolocation, since that needs no
            // external lookup/database and matches in practice for the vast majority of
            // visitors. Stored in session so it's decided once, not re-parsed every request.
            $locale = $this->detectFromBrowser($request) ?? config('app.locale');
            session(['locale' => $locale]);
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        // Kept in sync on every authenticated request (not just an explicit switch — see
        // LocaleController) so a user's last-known language is always available for an email
        // some OTHER user's action sends them, which has no session of the recipient's own to
        // read at send time (see AdminSuggestionApprovedMail's own use of this column).
        $user = $request->user();

        if ($user !== null && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }

        return $next($request);
    }

    /** First Indonesian or English language tag in the browser's Accept-Language header, if any. */
    private function detectFromBrowser(Request $request): ?string
    {
        $header = (string) $request->server('HTTP_ACCEPT_LANGUAGE', '');

        foreach (explode(',', $header) as $tag) {
            $primary = Str::lower(trim(explode('-', trim(explode(';', $tag)[0]))[0]));

            if (in_array($primary, ['id', 'en'], true)) {
                return $primary;
            }
        }

        return null;
    }
}
