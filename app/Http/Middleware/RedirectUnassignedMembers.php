<?php

namespace App\Http\Middleware;

use App\Models\ChurchSocial;
use App\Models\Person;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectUnassignedMembers
{
    /**
     * Routes a freshly self-registered member (role === null) may reach — everything else
     * bounces to their own account page. Kept as an allow-list rather than a per-route
     * middleware so it can't be bypassed by simply forgetting to gate a new route.
     */
    private const ALLOWED_ROUTES = [
        'akun-saya',
        'logout',
        'locale.switch',
        'about',
        'profile.complete',
        'profile.complete.store',
        'profile.complete.skip',
        'link-person',
        'link-person.store',
        'churches.directory',
        'churches.show',
        'churches.analytics',
        'churches.metric-comparison',
        'churches.leaderboard',
        'churches.needs-attention',
        'churches.platform-comparison',
        'people.metric-comparison',
        'people.leaderboard',
        'people.platform-comparison',
        'institutions.show',
        'institutions.metric-comparison',
        'institutions.leaderboard',
        'institutions.platform-comparison',
        'profile.edit',
        'profile.update',
        'profile.password.update',
        'profile.verify-email',
        'profile.verify-email.attempt',
        'profile.verify-email.resend',
        'profile.verify-email.cancel',
        'people.show',
        'people.edit',
        'people.update',
        'people.socials.index',
        'people.socials.create',
        'people.socials.store',
        'socials.edit',
        'socials.update',
        'socials.destroy',
        'socials.stats.create',
        'socials.stats.store',
        'export.person.preview',
        'export.person.download',
        'export.social-history.preview',
        'export.social-history.download',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->role !== null) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (! in_array($routeName, self::ALLOWED_ROUTES, true) || ! $this->ownsBoundEntity($request, $routeName)) {
            return redirect()->route('akun-saya');
        }

        return $next($request);
    }

    /**
     * Routes that bind a {person}/{social} but are read-only views, not self-management —
     * PersonPolicy::view()/ChurchPolicy::view() are the actual authority for who a plain
     * member may look at here (any church, and per Analytics's new reach, any person too —
     * see BuildsLeaderboards::analyticsPersonScope()), so this middleware's stricter
     * self-only ownership check below is deliberately skipped for just these two.
     */
    private const VIEW_ONLY_ROUTES = ['people.show', 'churches.show', 'institutions.show'];

    /**
     * Route-model binding alone doesn't check ownership (anyone could type /personal/5),
     * so a member's own person_id must match whatever {person}/{social} the route resolved.
     * Must check for a bound entity BEFORE requiring the actor's own Person to exist — routes
     * with nothing bound (akun-saya, logout, locale.switch) don't need one, and a member
     * without a Person (a data-integrity edge case — see MyAccountController) hitting
     * akun-saya must not get redirected back to akun-saya, which would loop forever.
     */
    private function ownsBoundEntity(Request $request, ?string $routeName): bool
    {
        if (in_array($routeName, self::VIEW_ONLY_ROUTES, true)) {
            return true;
        }

        $routePerson = $request->route('person');
        $routeSocial = $request->route('social');

        if (! $routePerson && ! $routeSocial) {
            return true;
        }

        $person = $request->user()->person;

        if (! $person) {
            return false;
        }

        if ($routePerson instanceof Person) {
            return $routePerson->id === $person->id;
        }

        if ($routeSocial instanceof ChurchSocial) {
            return $routeSocial->person_id === $person->id;
        }

        return false;
    }
}
