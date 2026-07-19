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
     * Manual accounts (is_auto_fetch off) never get touched by FetchSingleChurchData,
     * so this is the only way their numbers ever get recorded.
     */
    public function create(ChurchSocial $social)
    {
        abort_if($social->is_auto_fetch, 404);

        $latest = $social->stats()->first();

        return view('socials.stat-form', [
            'social' => $social,
            'latest' => $latest,
        ]);
    }

    public function store(Request $request, ChurchSocial $social): RedirectResponse
    {
        abort_if($social->is_auto_fetch, 404);

        $rules = [
            'recorded_at' => ['required', 'date', 'before_or_equal:today'],
            'followers_count' => ['nullable', 'integer', 'min:0'],
            'following_count' => ['nullable', 'integer', 'min:0'],
            'posts_count' => ['nullable', 'integer', 'min:0'],
            'views_count' => ['nullable', 'integer', 'min:0'],
            'videos_count' => ['nullable', 'integer', 'min:0'],
            'likes_count' => ['nullable', 'integer', 'min:0'],
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

        $redirectRoute = $social->person_id ? 'people.show' : 'churches.show';
        $redirectEntity = $social->person_id ? $social->person : $social->church;

        return redirect()->route($redirectRoute, $redirectEntity)
            ->with('status', "Data manual untuk {$social->display_handle} berhasil disimpan.");
    }
}
