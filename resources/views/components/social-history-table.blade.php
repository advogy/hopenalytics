{{-- One social account's stat history table (newest first, capped by the caller — 30 rows
     today) — rendered by entity-social-summary inside its per-account tab panel, which owns the
     heading/account label now. Self-guards on empty history. The table body scrolls inside a
     fixed max height with a sticky header (per the user's explicit call: stacking every
     account's full table one after another made entity pages scroll far down as history grew). --}}
@props(['social', 'historyRows'])

@php
    $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count', 'x' => 'followers_count', 'threads' => 'followers_count'];
    // Same column set as socials/history-index.blade.php (the admin-only management view of
    // this same data) — every column here has a real, fetched church_stats value behind it,
    // including the same "views" the weekly growth score uses (see
    // BuildsLeaderboards::metricDefinition()), which this table previously never showed at all.
    $secondaryFields = [
        'youtube' => ['views_count' => 'Views', 'videos_count' => 'Videos'],
        'instagram' => ['recent_reels_views' => 'Views', 'recent_reels_comments' => 'Comments', 'recent_reels_count' => 'Reels', 'following_count' => 'Following', 'posts_count' => 'Posts'],
        'tiktok' => ['recent_video_plays' => 'Views', 'recent_video_comments' => 'Comments', 'recent_video_count' => 'Videos', 'recent_video_shares' => 'Shares', 'following_count' => 'Following', 'likes_count' => 'Likes', 'posts_count' => 'Posts'],
        'facebook' => ['following_count' => 'Following', 'recent_posts_count' => 'Posts', 'recent_posts_likes' => 'Likes', 'recent_posts_comments' => 'Comments', 'recent_posts_shares' => 'Shares'],
        'x' => ['following_count' => 'Following', 'posts_count' => 'Posts'],
        // No following-count field at all — see ThreadsStatsFetcher's own doc comment.
        'threads' => ['recent_posts_count' => 'Posts'],
    ];

    // Which of the 6 superadmin-toggleable metrics (Settings' "Metrik" tab) each column
    // belongs to — 'reach' (the primary Followers/Subscribers column above) is always shown,
    // same as an entity's own name is never hidden; a column absent from this map (Following,
    // Reels/Videos count) isn't part of the metric-visibility system at all and always shows.
    $metricKeyForColumn = [
        'views_count' => 'views', 'recent_reels_views' => 'views', 'recent_video_plays' => 'views',
        'likes_count' => 'likes', 'recent_posts_likes' => 'likes',
        'recent_reels_comments' => 'comments', 'recent_video_comments' => 'comments', 'recent_posts_comments' => 'comments',
        'recent_video_shares' => 'shares', 'recent_posts_shares' => 'shares',
        'posts_count' => 'posts', 'videos_count' => 'posts', 'recent_posts_count' => 'posts',
    ];
    $enabledMetrics = \App\Models\AppSetting::current()->enabledMetricValues();
    $visibleSecondaryFields = collect($secondaryFields[$social->platform->value])
        ->filter(fn ($label, $field) => ! isset($metricKeyForColumn[$field]) || in_array($metricKeyForColumn[$field], $enabledMetrics, true));
@endphp

@if ($historyRows->isNotEmpty())
    <div>
        <div class="max-h-96 overflow-auto rounded-2xl border border-black/5 dark:border-white/5">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">{{ __('common.date') }}</th>
                        @if (in_array('reach', $enabledMetrics, true))
                            <th class="whitespace-nowrap px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">
                                {{ $social->platform === \App\Enums\SocialPlatform::YouTube ? 'Subscribers' : 'Followers' }}
                            </th>
                        @endif
                        @foreach ($visibleSecondaryFields as $label)
                            <th class="whitespace-nowrap px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                    @foreach ($historyRows as $row)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-2.5 tabular-nums">{{ $row->recorded_at->translatedFormat('d M Y') }}</td>
                            @if (in_array('reach', $enabledMetrics, true))
                                <td class="px-4 py-2.5 font-medium tabular-nums">{{ number_format($row->{$countField[$social->platform->value]} ?? 0) }}</td>
                            @endif
                            @foreach ($visibleSecondaryFields as $secField => $label)
                                <td class="px-4 py-2.5 tabular-nums">{{ number_format($row->{$secField} ?? 0) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
