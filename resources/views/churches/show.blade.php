@php
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
    $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count'];
    $secondaryFields = [
        'youtube' => ['views_count' => 'Views', 'videos_count' => 'Videos'],
        'instagram' => ['following_count' => 'Following', 'posts_count' => 'Posts'],
        'tiktok' => ['following_count' => 'Following', 'likes_count' => 'Likes', 'posts_count' => 'Posts'],
        'facebook' => [],
    ];
    $categoryLabels = ['gereja' => __('directory.church_accounts'), 'umum' => __('directory.general_accounts')];
    $statusMeta = [
        'success' => ['label' => __('entity.status_auto'), 'icon' => 'check-circle', 'classes' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
        'failed' => ['label' => __('entity.status_failed'), 'icon' => 'x-circle', 'classes' => 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-400'],
        'pending' => ['label' => __('entity.status_pending'), 'icon' => 'clock', 'classes' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'],
        'manual' => ['label' => __('entity.status_manual'), 'icon' => 'pencil-square', 'classes' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400'],
    ];
    $socialsByCategory = $church->socials->groupBy(fn ($social) => $social->category->value);
@endphp

@extends('layouts.app')

@section('title', $church->name . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('churches.directory', ['tab' => 'gereja']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('entity.all_churches') }}
    </a>

    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $church->name }}</h1>
            @if ($church->city)
                <p class="text-slate-500 dark:text-slate-400">{{ $church->city }}</p>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('churches.edit', $church) }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                {{ __('entity.edit_church') }}
            </a>
            <x-export-button :url="route('export.church.preview', $church)" />
            <a
                href="{{ route('socials.create', $church) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
            >
                {{ __('entity.add_account') }}
            </a>
        </div>
    </div>

    @if ($church->socials->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
            <p class="text-slate-500 dark:text-slate-400">{{ __('entity.no_socials') }}</p>
        </div>
    @endif

    @if ($church->socials->isNotEmpty())
        <div class="mb-8 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
            @if (empty($scoreHistory))
                <p class="font-bold text-slate-900 dark:text-white">{{ __('entity.growth_score_title') }}</p>
                <p class="mt-1 text-sm text-slate-400 dark:text-slate-500">{{ __('entity.growth_score_no_data') }}</p>
            @else
                @php $currentScore = end($scoreHistory); @endphp
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="font-bold text-slate-900 dark:text-white">{{ __('entity.growth_score_title') }}</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('entity.growth_score_subtitle') }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-4">
                        <x-sparkline :values="$scoreHistory" class="h-8 w-28" />
                        <span class="text-2xl font-bold tabular-nums {{ $currentScore > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($currentScore < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                            {{ $currentScore > 0 ? '+' : '' }}{{ number_format($currentScore, 1) }}%
                        </span>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @foreach (['gereja', 'umum'] as $category)
        @continue($socialsByCategory->get($category, collect())->isEmpty())

        <h2 class="mb-3 mt-8 text-lg font-medium first:mt-0">{{ $categoryLabels[$category] }}</h2>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($socialsByCategory[$category] as $social)
                @php
                    $rows = $history[$social->id] ?? collect();
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

                                <a
                                    href="{{ route('socials.edit', $social) }}"
                                    title="{{ __('entity.edit_account_title') }}"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-blue-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-400"
                                >
                                    <x-icon name="pencil-square" class="h-3.5 w-3.5" />
                                </a>

                                @if ($social->is_auto_fetch)
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
                                @else
                                    <a
                                        href="{{ route('socials.stats.create', $social) }}"
                                        title="{{ __('entity.manual_stat_link') }}"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-blue-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-400"
                                    >
                                        <x-icon name="plus" class="h-3.5 w-3.5" />
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($latest)
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-3xl font-semibold tracking-tight">
                                    {{ number_format($latest->{$field} ?? 0) }}
                                </p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ $social->platform === \App\Enums\SocialPlatform::YouTube ? __('entity.subscriber') : __('entity.followers') }}
                                </p>
                            </div>

                            <x-sparkline :values="$sparklineValues" class="h-7 w-[88px] text-slate-300 dark:text-slate-600" />
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

                        @if ($social->platform->value === 'instagram' && $latest->recent_reels_count)
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

                        @if ($social->platform->value === 'tiktok' && $latest->recent_video_count)
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
                    @else
                        <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('entity.no_stats_yet') }}</p>
                    @endif

                    @if ($social->last_fetch_error)
                        <p class="mt-3 text-xs text-red-600 dark:text-red-400">{{ $social->last_fetch_error }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

    @foreach ($church->socials as $social)
        @php $rows = $history[$social->id] ?? collect(); @endphp
        @if ($rows->isNotEmpty())
            <div class="mt-8">
                <h2 class="mb-3 flex items-center gap-2 font-medium">
                    <x-platform-icon :platform="$social->platform" class="h-6 w-6 text-xs" />
                    {{ __('entity.history_heading', ['platform' => $platformLabels[$social->platform->value]]) }}
                    <span class="font-normal text-slate-500 dark:text-slate-400">{{ $social->display_handle }}</span>
                    <span class="text-sm font-normal text-slate-400 dark:text-slate-500">({{ $categoryLabels[$social->category->value] }})</span>
                </h2>
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-2.5 tabular-nums">{{ $row->recorded_at->translatedFormat('d M Y') }}</td>
                                    <td class="px-4 py-2.5 font-medium tabular-nums">{{ number_format($row->{$countField[$social->platform->value]} ?? 0) }}</td>
                                    @foreach ($secondaryFields[$social->platform->value] as $secField => $label)
                                        <td class="px-4 py-2.5 tabular-nums">{{ number_format($row->{$secField} ?? 0) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach
@endsection
