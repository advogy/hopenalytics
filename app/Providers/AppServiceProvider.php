<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
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

        Gate::define('manage-queue', fn (User $user) => $user->role?->hasNasionalAccess() ?? false);
        Gate::define('manage-settings', fn (User $user) => $user->role?->hasNasionalAccess() ?? false);

        // Fetching fresh data spends paid Apify credits per call regardless of scope
        // (decision #3), so both single and bulk refresh are nasional-level only.
        Gate::define('trigger-refresh', fn (User $user) => $user->role?->hasNasionalAccess() ?? false);

        Gate::define('manage-hierarchy', fn (User $user) => $user->role?->hasNasionalAccess() ?? false);

        // Institutions aren't nested under a single Union/Conference (per UserRole::level()),
        // so — unlike admin_uni/admin_daerah/admin_gereja — their admins are assigned
        // directly by nasional-level actors instead of delegated down the chain.
        Gate::define('manage-institution-users', fn (User $user) => $user->role?->hasNasionalAccess() ?? false);

        // Any role that can promote at all (i.e. not read-only, not gereja-level, not a
        // plain member) may reach the delegated user-assignment page.
        Gate::define('delegate-users', fn (User $user) => $user->role?->promotesToLevel() !== null);

        // Directory & Analytics: superadmin/admin_nasional/admin_uni/admin_daerah only —
        // deliberately excludes admin_gereja and every Pimpinan (read-only) role, per the
        // user's explicit call. Each admin still only sees their own region (visibleTo()).
        Gate::define('browse-directory-analytics', fn (User $user) => $user->role !== null
            && ! $user->role->isReadOnly()
            && $user->role->level() !== 'gereja');
    }
}
