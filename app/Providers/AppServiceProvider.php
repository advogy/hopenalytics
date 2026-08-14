<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // DB-stored credentials (set via Pengaturan → Apify & Auto-Fetch, see
        // SettingsController) take priority over their .env counterparts — these are the one
        // and only read path ApifyClient/YouTubeStatsFetcher fall back to
        // (config('services.apify.token')/config('services.youtube.api_key'), see each class's
        // constructor), so overriding them here means either key can be rotated from the UI
        // without touching .env or redeploying, per the user's explicit call. Only overrides
        // when a DB value is actually set, so a fresh install (or one where nobody's used the
        // settings UI yet) keeps working off .env exactly as before.
        $appSettings = AppSetting::current();

        if ($appSettings->apify_token) {
            config(['services.apify.token' => $appSettings->apify_token]);
        }

        if ($appSettings->youtube_api_key) {
            config(['services.youtube.api_key' => $appSettings->youtube_api_key]);
        }

        // Feeds the Audit Log page's Login/Session tab (see AuditLogController) — Laravel
        // fires these two events on every Auth::login()/logout() call regardless of whether
        // anything listens, so this is the only hook needed to start recording history; nothing
        // existed here before. A session that simply expires or is abandoned (browser closed,
        // no explicit "Sign Out") never fires Logout, so that row's logged_out_at stays null
        // forever — there's no reliable server-side signal for that case, so it's left honest
        // rather than guessed at. latest()->first() closes the most recently opened still-open
        // session for that user rather than tracking a specific session ID, which is a
        // reasonable simplification for a single-session-per-user usage pattern but could
        // misattribute which session closed if the same user is logged in on multiple devices
        // at once.
        Event::listen(Login::class, function (Login $event) {
            LoginLog::create([
                'user_id' => $event->user->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            if (! $event->user) {
                return;
            }

            LoginLog::where('user_id', $event->user->id)
                ->whereNull('logged_out_at')
                ->latest()
                ->first()
                ?->update(['logged_out_at' => now()]);
        });

        // Keyed by identity + IP (Laravel's standard login-throttling shape) rather than IP
        // alone, so brute-forcing one account isn't merely a matter of rotating IPs against a
        // wide-open endpoint, while a shared IP (office/NAT) doesn't lock out unrelated users.
        RateLimiter::for('login', fn ($request) => Limit::perMinute(5)
            ->by(Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip())));

        // OTP/reset codes are 6-digit (1M possibilities) with a 10-minute expiry — 5
        // attempts/minute caps a brute-force run at ~50 guesses per code, statistically
        // negligible odds of success. Keyed by the session-established pending user (not
        // request input, since these endpoints only take the code) + IP.
        RateLimiter::for('verify-otp', fn ($request) => Limit::perMinute(5)
            ->by(($request->session()->get('otp_user_id') ?? 'guest').'|'.$request->ip()));

        RateLimiter::for('reset-password', fn ($request) => Limit::perMinute(5)
            ->by(($request->session()->get('password_reset_user_id') ?? 'guest').'|'.$request->ip()));

        // Same 6-digit-OTP brute-force shape as verify-otp/reset-password above, but this one
        // is already behind auth — keyed by the authenticated user directly rather than a
        // pre-auth session value.
        RateLimiter::for('verify-email-change', fn ($request) => Limit::perMinute(5)
            ->by($request->user()->id.'|'.$request->ip()));

        Gate::define('manage-queue', fn (User $user) => $user->role?->hasGlobalAccess() ?? false);
        Gate::define('manage-settings', fn (User $user) => $user->role?->hasGlobalAccess() ?? false);

        // Setting the national reach/views/likes/posts targets (evenly divided down the Uni >
        // Daerah chain for the dashboard's Goal widget) is nasional-level only, same threshold
        // as manage-queue/manage-settings above.
        Gate::define('manage-goals', fn (User $user) => $user->role?->hasGlobalAccess() ?? false);

        // Fetching fresh data spends paid Apify credits per call regardless of scope
        // (decision #3), so both single and bulk refresh are nasional-level only.
        Gate::define('trigger-refresh', fn (User $user) => $user->role?->hasGlobalAccess() ?? false);

        // Governs the Uni/Daerah/Gereja/Institusi CRUD sub-routes nested under Kelola Akun
        // (create/edit/toggle/delete/socials for each), NOT the page itself anymore — Kelola
        // Akun's own route (admin.accounts.index) is gated by manage-people below instead,
        // since that page also holds the Personal tab, which was never gereja-excluded. This
        // gate is open to any non-read-only, non-gereja-level role — same threshold as browse-
        // directory-analytics/ChurchPolicy::create(). What each visitor actually sees and can
        // act on is scoped per-entity by visibleTo() (query-time) and the Union/Conference/
        // Church/InstitutionPolicy (action-time) — an admin_uni only ever sees/manages what's
        // under their own Uni, admin_daerah their own Daerah, admin_institusi their own
        // Institusi, per the user's explicit call. admin_gereja stays excluded here — their one
        // church is reached via the "Gereja Saya" nav link instead (see layouts.app), and
        // managed from there via churches.show's own "Kelola Akun" shortcut (churches.socials.
        // index, gated separately by can:update,church).
        Gate::define('manage-hierarchy', fn (User $user) => $user->role !== null
            && ! $user->role->isReadOnly()
            && $user->role->level() !== 'gereja');

        // Institutions aren't nested under a single Union/Conference (per UserRole::level()),
        // so — unlike admin_uni/admin_daerah/admin_gereja — their admins are assigned
        // directly by global- or nasional-level actors instead of delegated down the chain.
        // Admin Nasional keeps this reach even though it's now scoped to an assigned Union
        // set elsewhere, per the user's explicit call — institutions aren't Union-scoped, so
        // there's nothing to narrow here.
        Gate::define('manage-institution-users', fn (User $user) => $user->role?->hasGlobalAccess()
            || $user->role === UserRole::AdminNasional);

        // Any role that can promote at all (i.e. not read-only, not gereja-level, not a
        // plain member) may reach the delegated user-assignment page.
        Gate::define('delegate-users', fn (User $user) => $user->role?->promotesToLevel() !== null);

        // Exporting (directory, analytics, leaderboards, comparisons) stays superadmin/
        // admin_global/admin_nasional/admin_divisi/admin_uni/admin_daerah only, per the user's
        // explicit call — deliberately excludes admin_gereja, admin_institusi, and every
        // Pimpinan (read-only) role. level() is null for SuperAdmin and 'global' for Admin
        // Global (see UserRole::level()), hence both being in the allow-list below alongside
        // the named levels. *Viewing* the Analytics page itself is a separate, broader gate —
        // see view-analytics below.
        Gate::define('browse-directory-analytics', fn (User $user) => $user->role !== null
            && ! $user->role->isReadOnly()
            && in_array($user->role->level(), [null, 'global', 'nasional', 'divisi', 'uni', 'daerah'], true));

        // Viewing Analytics & Statistik (as opposed to exporting it — browse-directory-
        // analytics above) is open to a plain member, admin_gereja, admin_daerah, and
        // admin_uni too, per the user's explicit call — "so information still gets through,
        // people can see progress, within their own limits". Only Pimpinan (read-only) roles
        // stay excluded; they weren't named. What each of these newly-included viewers
        // actually sees is scoped separately by BuildsLeaderboards::analyticsChurchScope()/
        // analyticsPersonScope() — a plain member sees everything unscoped (they have no
        // region), admin_gereja sees their whole Daerah/Konferens (same breadth as
        // admin_daerah, not just their one church), everyone else keeps their normal
        // visibleTo() scope.
        Gate::define('view-analytics', fn (User $user) => $user->role === null || ! $user->role->isReadOnly());

        // Just *viewing* the directory (as opposed to exporting it, or the fuller Analytics
        // page) is open to literally any logged-in member — including plain, unassigned ones
        // (role === null) — not just admin_gereja/Pimpinan, per the user's explicit call:
        // "someone might just want to follow the account". It's a public listing, not an
        // admin management view, so it's deliberately unscoped by region for everyone
        // (ChurchDashboardController::directory() no longer applies visibleTo() at all) —
        // actually managing an entity (adding/editing its socials) still requires can:update
        // on the church/person/social, which stays scoped, on that entity's own detail page.
        Gate::define('view-directory', fn (User $user) => true);

        // Reaching Kelola Akun's Personal tab (and people.create) only requires the same
        // threshold as PersonPolicy::create() — any non-read-only role, including admin_gereja.
        // Unlike browse-directory-analytics, this deliberately does NOT exclude gereja-level
        // admins: a Person isn't bound to a single church (decision #2 in PersonPolicy), so
        // admin_gereja legitimately needs to create/manage personal accounts too, not just
        // Uni/Daerah/Nasional. Being the broader of the two gates that used to guard "Kelola
        // Organisasi" and "Kelola Personal" as separate pages (manage-hierarchy above additionally
        // excludes gereja-level), this is also what the merged Kelola Akun page itself
        // (admin.accounts.index) is gated by — see routes/web.php. AccountController::index()
        // still scopes the Personal tab's actual list via visibleTo(), so an admin_gereja
        // reaching the page only ever sees their own region's people there, same as any other
        // admin level, and sees no organization tab at all (manage-hierarchy still excludes them
        // from those).
        Gate::define('manage-people', fn (User $user) => $user->role !== null && ! $user->role->isReadOnly());

        // A soft-deleted User row still physically exists and still trips restrictOnDelete FK
        // constraints elsewhere (Union/Conference/Church/Institution can't be hard-deleted
        // while one still points at them) — regular admins can only soft-delete (destroy())
        // via UserAssignmentController, so only Superadmin can reach in and either restore one
        // or purge it for good, per the user's explicit call.
        Gate::define('manage-deleted-users', fn (User $user) => $user->role === UserRole::SuperAdmin);

        // Audit log (role promote/revoke, user lifecycle, org-unit CRUD — see AuditLogger)
        // stays Superadmin-only for now, per the user's explicit call ("audit log khusus
        // untuk superadmin saja dulu") — not even Admin Nasional, unlike most other
        // nasional-level gates in this file.
        Gate::define('view-audit-log', fn (User $user) => $user->role === UserRole::SuperAdmin);

        // Editing/deleting an individual historical data point (ChurchStat row) rewrites a
        // church/person/organization's recorded growth numbers directly — Superadmin-only, same
        // threshold as view-audit-log above, per the user's explicit call ("untuk superadmin").
        // Adding a NEW data point (socials.stats.create/store) stays open to anyone who can
        // manage the account (can:update,social) — this gate only covers correcting/removing
        // one that's already recorded.
        Gate::define('manage-social-history', fn (User $user) => $user->role === UserRole::SuperAdmin);

        // Which platforms the whole app tracks at all — a global kill switch, so it stays
        // Superadmin-only same as the two gates above it, per the user's explicit call
        // ("ini hanya ada di superadmin").
        Gate::define('manage-platform-visibility', fn (User $user) => $user->role === UserRole::SuperAdmin);
    }
}
