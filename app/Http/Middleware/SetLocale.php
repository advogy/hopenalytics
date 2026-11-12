<?php

namespace App\Http\Middleware;

use App\Support\IndonesiaIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale');

        if (! in_array($locale, ['id', 'en'], true)) {
            // First visit — no explicit choice stored yet (see LocaleController, which
            // always wins once a visitor actually uses the language switcher). Chosen by
            // where the visitor is: Indonesian inside Indonesia, English everywhere else.
            // Stored in session so it's decided once, not looked up on every request.
            $locale = $this->detectFromLocation($request);
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

    /**
     * 'id' for an IP address in Indonesia, 'en' for any other public address. A loopback or
     * private address (local development) has no location, so it gets APP_LOCALE instead.
     */
    private function detectFromLocation(Request $request): string
    {
        $ip = (string) $request->ip();

        if (! IndonesiaIp::isPublic($ip)) {
            return config('app.locale') === 'en' ? 'en' : 'id';
        }

        return IndonesiaIp::contains($ip) ? 'id' : 'en';
    }
}
