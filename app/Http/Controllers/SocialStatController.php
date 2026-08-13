<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Models\ChurchSocial;
use App\Models\ChurchStat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocialStatController extends Controller
{
    /**
     * Always reachable for whoever can manage the account (see the route's own can:update,social
     * middleware) — an admin can fill in or override a data point by hand whenever they want,
     * whether that's a manual-only account (is_auto_fetch off — the only way its numbers ever
     * get recorded at all) or an auto-fetch one they'd rather not wait on right now.
     */
    public function create(ChurchSocial $social)
    {
        $latest = $social->stats()->first();

        return view('socials.stat-form', [
            'social' => $social,
            'latest' => $latest,
            'editing' => null,
        ]);
    }

    public function store(Request $request, ChurchSocial $social): RedirectResponse
    {
        $rules = [
            'recorded_at' => ['required', 'date', 'before_or_equal:today'],
            'followers_count' => ['nullable', 'integer', 'min:0'],
            'following_count' => ['nullable', 'integer', 'min:0'],
            'posts_count' => ['nullable', 'integer', 'min:0'],
            'views_count' => ['nullable', 'integer', 'min:0'],
            'videos_count' => ['nullable', 'integer', 'min:0'],
            'likes_count' => ['nullable', 'integer', 'min:0'],
            'recent_posts_count' => ['nullable', 'integer', 'min:0'],
        ];

        $data = $request->validate($rules);
        $recordedAt = $data['recorded_at'];
        unset($data['recorded_at']);

        // Mirrors FetchSingleChurchData's own field naming: YouTube tracks "subscribers",
        // everyone else tracks "followers" — same column split as the rest of the app.
        if ($social->platform === SocialPlatform::YouTube) {
            $data['subscribers_count'] = $data['followers_count'] ?? null;
            unset($data['followers_count']);
        }

        ChurchStat::updateOrCreate(
            ['church_social_id' => $social->id, 'recorded_at' => $recordedAt],
            $data,
        );

        $social->update(['last_fetched_at' => now(), 'last_fetch_status' => 'success', 'last_fetch_error' => null]);

        [$redirectRoute, $redirectEntity] = $social->showRoute();

        return redirect()->route($redirectRoute, $redirectEntity)
            ->with('status', __('entity.manual_stat_saved', ['handle' => $social->display_handle]));
    }

    /**
     * Superadmin-only (see the route's can:manage-social-history middleware) — every recorded
     * weekly data point for one account, oldest-hidden-behind-pagination, newest first, with
     * edit/delete actions per row. Reached from a "Riwayat" link on the account row wherever
     * Kelola Akun lists accounts (churches/people/organization social-list) — see
     * social-account-row.blade.php.
     */
    public function history(ChurchSocial $social)
    {
        $stats = $social->stats()->paginate(30);

        return view('socials.history-index', [
            'social' => $social,
            'stats' => $stats,
        ]);
    }

    /**
     * Reuses socials/stat-form.blade.php in "edit" mode (see $editing there) instead of a
     * separate view — same fields, same per-platform shape, just prefilled from this exact row
     * (not the account's latest) and posting to update() below instead of store().
     */
    public function editStat(ChurchStat $stat)
    {
        return view('socials.stat-form', [
            'social' => $stat->churchSocial,
            'latest' => $stat,
            'editing' => $stat,
        ]);
    }

    /**
     * Updates this exact row by primary key — unlike store()'s updateOrCreate-by-date (which
     * only ever adds/overwrites "today's" or a freshly-chosen date's point), editing must not
     * let changing the date silently leave the original row behind as an untouched duplicate.
     * ignore($stat->id) on the recorded_at uniqueness check lets the date stay unchanged (the
     * common case) without tripping over its own row, while still catching a genuine collision
     * with a DIFFERENT existing row for this account (the DB has a hard unique index on
     * (church_social_id, recorded_at) — see church_stats' migration — so this must be caught
     * here with a clean message rather than a raw SQL crash on save).
     */
    public function update(Request $request, ChurchStat $stat): RedirectResponse
    {
        $social = $stat->churchSocial;

        $rules = [
            'recorded_at' => [
                'required',
                'date',
                'before_or_equal:today',
                Rule::unique('church_stats', 'recorded_at')
                    ->where(fn ($query) => $query->where('church_social_id', $social->id))
                    ->ignore($stat->id),
            ],
            'followers_count' => ['nullable', 'integer', 'min:0'],
            'following_count' => ['nullable', 'integer', 'min:0'],
            'posts_count' => ['nullable', 'integer', 'min:0'],
            'views_count' => ['nullable', 'integer', 'min:0'],
            'videos_count' => ['nullable', 'integer', 'min:0'],
            'likes_count' => ['nullable', 'integer', 'min:0'],
            'recent_posts_count' => ['nullable', 'integer', 'min:0'],
        ];

        $data = $request->validate($rules, [
            'recorded_at.unique' => __('entity.stat_date_taken'),
        ]);

        if ($social->platform === SocialPlatform::YouTube) {
            $data['subscribers_count'] = $data['followers_count'] ?? null;
            unset($data['followers_count']);
        }

        $stat->update($data);

        return redirect()->route('socials.history.index', $social)
            ->with('status', __('entity.manual_stat_updated', ['handle' => $social->display_handle]));
    }

    /**
     * A real, permanent delete (unlike ChurchSocialController::destroy(), which only deactivates
     * the account) — this removes one specific recorded data point outright, per the user's
     * explicit call for superadmin to be able to remove bad/mistaken history entries.
     */
    public function destroy(ChurchStat $stat): RedirectResponse
    {
        $social = $stat->churchSocial;
        $stat->delete();

        return redirect()->route('socials.history.index', $social)
            ->with('status', __('entity.manual_stat_deleted'));
    }
}
