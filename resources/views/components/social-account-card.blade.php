{{-- One social account's full stat card — platform, handle, status badge, export/refresh/
     manual-entry actions, latest value + sparkline, delta, secondary fields, and recent-content
     preview (Instagram reels / TikTok videos). Shared by churches/show, institutions/show, and
     people/show, which used to each carry their own near-identical ~150-line copy of this.
     $showRecentContent is false only for people.show — personal accounts never showed the
     recent-reels/recent-videos blocks even before this was extracted, so this preserves that
     rather than silently changing behavior. --}}
@props(['social', 'historyRows' => null, 'showRecentContent' => true])

@php
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
    $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count'];
    $secondaryFields = [
        'youtube' => ['views_count' => 'Views', 'videos_count' => 'Videos'],
        'instagram' => ['following_count' => 'Following', 'posts_count' => 'Posts'],
        'tiktok' => ['following_count' => 'Following', 'likes_count' => 'Likes', 'posts_count' => 'Posts'],
        'facebook' => [],
    ];
    $statusMeta = [
        'success' => ['label' => __('entity.status_auto'), 'icon' => 'check-circle', 'classes' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
        'failed' => ['label' => __('entity.status_failed'), 'icon' => 'x-circle', 'classes' => 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-400'],
        'pending' => ['label' => __('entity.status_pending'), 'icon' => 'clock', 'classes' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'],
        'manual' => ['label' => __('entity.status_manual'), 'icon' => 'pencil-square', 'classes' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400'],
    ];

    $rows = $historyRows ?? collect();
    $field = $countField[$social->platform->value];
    $latest = $rows->first();
    $previous = $rows->get(1);
    $delta = ($latest && $previous) ? ($latest->{$field} - $previous->{$field}) : null;
    $sparklineValues = $rows->reverse()->values()->pluck($field)->all();

    $statusKey = match (true) {
        ! $social->is_auto_fetch => 'manual',
        $social->last_fetch_status === 'failed' => 'failed',
        $social->last_fetched_at !== null => 'success',
        default => 'pending',
    };
    $status = $statusMeta[$statusKey];

    $externalUrl = $social->externalUrl();
@endphp

<div class="group rounded-2xl border border-black/5 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-white/5 dark:bg-slate-900">
    <div class="mb-4 flex items-start justify-between gap-2">
        <a
            @if ($externalUrl) href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer" @endif
            class="flex min-w-0 items-center gap-3"
        >
            <x-platform-icon :platform="$social->platform" class="h-10 w-10 shrink-0 text-lg" />
            <div class="min-w-0">
                <p class="flex items-center gap-1 font-medium">
                    {{ $platformLabels[$social->platform->value] }}
                    @if ($externalUrl)
                        <x-icon name="arrow-top-right-on-square" class="h-3.5 w-3.5 shrink-0 text-slate-300 transition group-hover:text-blue-500 dark:text-slate-600" />
                    @endif
                </p>
                <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $social->display_handle }}</p>
            </div>
        </a>

        <div class="flex shrink-0 flex-col items-end gap-1.5">
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium {{ $status['classes'] }}">
                <x-icon :name="$status['icon']" class="h-3.5 w-3.5" />
                {{ $status['label'] }}
            </span>

            <div class="flex items-center gap-1.5">
                <a
                    href="{{ route('export.social-history.preview', $social) }}"
                    data-export-trigger
                    title="{{ __('entity.export_history_title') }}"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-blue-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-400"
                >
                    <x-icon name="arrow-down-tray" class="h-3.5 w-3.5" />
                </a>

                @if ($social->is_auto_fetch)
                    @can('trigger-refresh')
                        @php
                            $usesThirdPartyCredit = in_array($social->platform->value, ['instagram', 'tiktok'], true);
                            $refreshConfirm = $usesThirdPartyCredit
                                ? __('entity.refresh_confirm_credit')
                                : __('entity.refresh_confirm_youtube');
                        @endphp
                        <div class="flex flex-col items-center gap-1">
                            <form method="POST" action="{{ route('socials.refresh', $social) }}" data-confirm="{{ $refreshConfirm }}" data-inline-refresh-form>
                                @csrf
                                <button
                                    type="submit"
                                    data-inline-refresh-button
                                    title="{{ __('entity.refresh_data_title') }}"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-400"
                                >
                                    <x-icon name="arrow-path" class="h-3.5 w-3.5" />
                                </button>
                            </form>
                            <div data-inline-refresh-bar class="hidden h-0.5 w-5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div class="h-full w-full animate-pulse rounded-full bg-blue-600"></div>
                            </div>
                        </div>
                    @endcan
                @endif

                {{--
                    Always available alongside the refresh button above (not just once auto-fetch
                    is off or has failed) — an admin can choose manual or automatic at any time,
                    whichever suits, rather than being forced to wait for auto-fetch or a failure
                    before a data point can be entered by hand.
                --}}
                @can('update', $social)
                    <a
                        href="{{ route('socials.stats.create', $social) }}"
                        title="{{ __('entity.manual_stat_link') }}"
                        class="inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-blue-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-400"
                    >
                        <x-icon name="plus" class="h-3.5 w-3.5" />
                    </a>
                @endcan
            </div>
        </div>
    </div>

    @if ($latest)
        <div class="flex items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="text-3xl font-semibold tracking-tight">
                    {{ number_format($latest->{$field} ?? 0) }}
                </p>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $social->platform === \App\Enums\SocialPlatform::YouTube ? __('entity.subscriber') : __('entity.followers') }}
                </p>
            </div>

            <x-sparkline :values="$sparklineValues" class="h-7 w-[88px] shrink-0 text-slate-300 dark:text-slate-600" />
        </div>

        <div class="mt-2 flex items-center gap-3 text-sm">
            @if ($delta !== null)
                <span class="inline-flex items-center gap-1 font-medium {{ $delta > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($delta < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                    <x-icon :name="$delta > 0 ? 'arrow-trending-up' : ($delta < 0 ? 'arrow-trending-down' : 'minus-small')" class="h-3.5 w-3.5" />
                    {{ $delta > 0 ? '+' : '' }}{{ number_format($delta) }}
                </span>
            @endif
            <span class="text-slate-400 dark:text-slate-500">{{ __('entity.as_of', ['date' => $latest->recorded_at->translatedFormat('d M Y')]) }}</span>
        </div>

        @if (! empty($secondaryFields[$social->platform->value]))
            <dl class="mt-4 flex gap-5 border-t border-slate-100 pt-4 text-sm dark:border-slate-800">
                @foreach ($secondaryFields[$social->platform->value] as $secField => $label)
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                        <dd class="font-medium tabular-nums">{{ number_format($latest->{$secField} ?? 0) }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if ($showRecentContent && $social->platform->value === 'instagram' && $latest->recent_reels_count)
            <div class="mt-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                <p class="mb-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('entity.recent_reels', ['count' => $latest->recent_reels_count]) }}
                </p>
                <p class="text-sm">
                    <span class="font-semibold tabular-nums">{{ number_format($latest->recent_reels_views) }}</span>
                    <span class="text-slate-500 dark:text-slate-400">views</span>
                </p>
            </div>
        @endif

        @if ($showRecentContent && $social->platform->value === 'tiktok' && $latest->recent_video_count)
            <div class="mt-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                <p class="mb-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('entity.recent_videos', ['count' => $latest->recent_video_count]) }}
                </p>
                <p class="text-sm">
                    <span class="font-semibold tabular-nums">{{ number_format($latest->recent_video_plays) }}</span>
                    <span class="text-slate-500 dark:text-slate-400">plays</span>
                    &middot;
                    <span class="font-semibold tabular-nums">{{ number_format($latest->recent_video_shares) }}</span>
                    <span class="text-slate-500 dark:text-slate-400">shares</span>
                </p>
            </div>
        @endif

        @if ($showRecentContent && $social->platform->value === 'facebook' && $latest->recent_posts_count)
            <div class="mt-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                <p class="mb-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('entity.recent_posts', ['count' => $latest->recent_posts_count]) }}
                </p>
                <p class="text-sm">
                    <span class="font-semibold tabular-nums">{{ number_format($latest->recent_posts_likes) }}</span>
                    <span class="text-slate-500 dark:text-slate-400">likes</span>
                    &middot;
                    <span class="font-semibold tabular-nums">{{ number_format($latest->recent_posts_shares) }}</span>
                    <span class="text-slate-500 dark:text-slate-400">shares</span>
                </p>
            </div>
        @endif
    @else
        <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('entity.no_stats_yet') }}</p>
    @endif

    @if ($social->last_fetch_error)
        <p class="mt-3 text-xs text-red-600 dark:text-red-400">{{ $social->last_fetch_error }}</p>
    @endif
</div>
