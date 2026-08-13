{{-- Superadmin-only management view of one account's full recorded history — see the route's
     can:manage-social-history middleware. Distinct from components/social-history-table.blade.php,
     which is the read-only version shown on churches/people/institutions/organizations' show
     pages; this one adds Edit/Delete per row and deliberately isn't shared with that component
     so the public-ish show pages stay untouched by an admin-only capability. --}}
@php
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'x' => 'X'];
    $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count', 'x' => 'followers_count'];
    // Every column here has a real, fetched church_stats value behind it (confirmed against
    // production data) — this table previously left several fetched fields with nowhere to be
    // seen at all: recent_reels_views/recent_video_plays (the same "views" the weekly growth
    // score and each platform's own account card use, see BuildsLeaderboards::metricDefinition()),
    // recent_reels_count/recent_video_count (how many reels/videos that views figure is sampled
    // from — context for reading it), recent_video_shares, and recent_posts_likes/shares
    // (Facebook — no data yet in this environment, but the column exists and is fetched for,
    // same as every other field here, so it's included for whenever that starts populating).
    $secondaryFields = [
        'youtube' => ['views_count' => 'Views', 'videos_count' => 'Videos'],
        'instagram' => ['recent_reels_views' => 'Views', 'recent_reels_count' => 'Reels', 'following_count' => 'Following', 'posts_count' => 'Posts'],
        'tiktok' => ['recent_video_plays' => 'Views', 'recent_video_count' => 'Videos', 'recent_video_shares' => 'Shares', 'following_count' => 'Following', 'likes_count' => 'Likes', 'posts_count' => 'Posts'],
        'facebook' => ['following_count' => 'Following', 'recent_posts_count' => 'Posts', 'recent_posts_likes' => 'Likes', 'recent_posts_shares' => 'Shares'],
        'x' => ['following_count' => 'Following', 'posts_count' => 'Posts'],
    ];
    // manageRoute(), not showRoute() — this is an admin-only management view (see docblock
    // above), so "back" should return to where the account is managed (same place
    // churches/social-form.blade.php's own back/cancel link goes), not to the entity's
    // public-ish show page.
    [$backRouteName, $owner] = $social->manageRoute();
@endphp

@extends('layouts.app')

@section('title', __('entity.history_manage_title') . ' — ' . $social->display_handle)

@section('content')
    <a href="{{ route($backRouteName, $owner) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('entity.back_to', ['name' => $owner->name]) }}
    </a>

    <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <x-platform-icon :platform="$social->platform" class="h-10 w-10 shrink-0 text-lg" />
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('entity.history_manage_title') }}</h1>
                <p class="text-slate-500 dark:text-slate-400">{{ $platformLabels[$social->platform->value] }} &middot; {{ $social->display_handle }}</p>
            </div>
        </div>

        <a
            href="{{ route('socials.stats.create', $social) }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
        >
            {{ __('entity.add_stat') }}
        </a>
    </div>

    @if ($stats->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
            <p class="text-slate-500 dark:text-slate-400">{{ __('entity.no_stats_yet') }}</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">{{ __('common.date') }}</th>
                        <th class="px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">
                            {{ $social->platform === \App\Enums\SocialPlatform::YouTube ? 'Subscribers' : 'Followers' }}
                        </th>
                        @foreach ($secondaryFields[$social->platform->value] as $label)
                            <th class="px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">{{ $label }}</th>
                        @endforeach
                        <th class="px-4 py-2.5 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                    @foreach ($stats as $stat)
                        <tr>
                            <td class="px-4 py-2.5 tabular-nums">{{ $stat->recorded_at->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-2.5 font-medium tabular-nums">{{ number_format($stat->{$countField[$social->platform->value]} ?? 0) }}</td>
                            @foreach ($secondaryFields[$social->platform->value] as $secField => $label)
                                <td class="px-4 py-2.5 tabular-nums">{{ number_format($stat->{$secField} ?? 0) }}</td>
                            @endforeach
                            <td class="px-4 py-2.5">
                                <div class="flex items-center justify-end gap-3">
                                    <a
                                        href="{{ route('socials.stats.edit', $stat) }}"
                                        title="{{ __('common.edit') }}"
                                        aria-label="{{ __('common.edit') }}"
                                        class="shrink-0 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        <x-icon name="pencil-square" class="h-5 w-5" />
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('socials.stats.destroy', $stat) }}"
                                        data-confirm="{{ __('entity.delete_stat_confirm', ['date' => $stat->recorded_at->translatedFormat('d M Y')]) }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            title="{{ __('common.delete') }}"
                                            aria-label="{{ __('common.delete') }}"
                                            class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            <x-icon name="trash" class="h-5 w-5" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$stats" />
    @endif
@endsection
