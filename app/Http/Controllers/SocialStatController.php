<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Models\ChurchSocial;
use App\Models\ChurchStat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
}
