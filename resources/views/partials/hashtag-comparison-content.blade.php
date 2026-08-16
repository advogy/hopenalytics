{{--
    Shared hashtag-comparison content — used both by the standalone Perbandingan Hastag page
    (churches/hashtag-comparison.blade.php) and the "Hastag" tab embedded in Analitik & Grafik
    (churches/analytics.blade.php), since the underlying data is identical everywhere — hashtag
    posts have no owner, so the same global data applies regardless of scope (see
    ChurchDashboardController::hashtagComparisonData()).

    Expected: $hashtags, $platforms, $lastUpdatedAt, $rows, $grandTotalByPlatform, $grandTotal,
    $posts (paginator, each row's churchSocial relation eager-loaded), $selectedHashtagId,
    $selectedPlatform.
    Optional: $formAction (omit for a self-submitting form on the current URL), $clearUrl (passed
    to x-filter-card), $platformParam (default 'platform' — override to avoid colliding with a
    host page's own "platform" query param, e.g. the Hastag tab on Analitik & Grafik),
    $hiddenFields (assoc array of extra <input type="hidden"> name => value, e.g. ['tab' =>
    'hastag'] so the filter form's GET submit stays on the right tab).
--}}
@php
    $platformParam = $platformParam ?? 'platform';
    $platformLabelsHashtag = \App\Models\AppSetting::current()->enabledPlatformLabels();
@endphp

@if ($hashtags->isEmpty())
    <x-empty-state>
        {{ __('hashtag.no_hashtags_tracked') }}
        @can('manage-settings')
            <a href="{{ route('admin.hashtags.index') }}" class="text-blue-600 hover:underline dark:text-blue-400">{{ __('nav.hashtags') }} →</a>
        @endcan
    </x-empty-state>
@else
    <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
        {{ $lastUpdatedAt ? __('dashboard.last_updated_at', ['time' => $lastUpdatedAt->translatedFormat('d M Y H:i')]) : __('dashboard.last_updated_never') }}
    </p>

    <div class="mb-8 overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_tag') }}</th>
                    @foreach ($platforms as $platform)
                        <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-platform-icon :platform="$platform" class="h-4 w-4" />
                                {{ $platformLabelsHashtag[$platform] }}
                            </div>
                        </th>
                    @endforeach
                    <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $row['hashtag']->display_tag }}</td>
                        @foreach ($platforms as $platform)
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['counts'][$platform]) }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format($row['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-black/5 bg-slate-50 dark:border-white/5 dark:bg-slate-800/60">
                <tr>
                    <td class="px-4 py-3 font-bold">{{ __('hashtag.grand_total_row') }}</td>
                    @foreach ($platforms as $platform)
                        <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($grandTotalByPlatform[$platform]) }}</td>
                    @endforeach
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($grandTotal) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <x-filter-card :clear-url="($selectedHashtagId || $selectedPlatform) ? ($clearUrl ?? null) : null">
        <form method="GET" @if (! empty($formAction)) action="{{ $formAction }}" @endif class="flex flex-wrap items-center gap-3">
            @foreach ($hiddenFields ?? [] as $hiddenName => $hiddenValue)
                <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
            @endforeach

            <label class="relative">
                <x-icon name="hashtag" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <select
                    name="hashtag"
                    onchange="this.form.submit()"
                    class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    <option value="">{{ __('hashtag.all_hashtags') }}</option>
                    @foreach ($hashtags as $hashtag)
                        <option value="{{ $hashtag->id }}" @selected((string) $selectedHashtagId === (string) $hashtag->id)>{{ $hashtag->display_tag }}</option>
                    @endforeach
                </select>
                <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            </label>

            <label class="relative">
                <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <select
                    name="{{ $platformParam }}"
                    onchange="this.form.submit()"
                    class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    <option value="">{{ __('hashtag.all_platforms') }}</option>
                    @foreach ($platforms as $platform)
                        <option value="{{ $platform }}" @selected($selectedPlatform === $platform)>{{ $platformLabelsHashtag[$platform] }}</option>
                    @endforeach
                </select>
                <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            </label>
        </form>
    </x-filter-card>

    <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.platform') }}</th>
                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_tag') }}</th>
                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_author') }}</th>
                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_caption') }}</th>
                    <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_engagement') }}</th>
                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_posted_at') }}</th>
                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                @forelse ($posts as $post)
                    <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <x-platform-icon :platform="$post->platform" class="h-4.5 w-4.5" />
                        </td>
                        <td class="px-4 py-3 font-medium">{{ $post->hashtag->display_tag }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            @if ($post->churchSocial)
                                @php [$ownerRouteName, $ownerRouteEntity] = $post->churchSocial->showRoute(); @endphp
                                <a href="{{ route($ownerRouteName, $ownerRouteEntity) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $post->churchSocial->display_name }}
                                </a>
                            @else
                                {{ $post->author_handle ?? '—' }}
                            @endif
                        </td>
                        <td class="max-w-xs px-4 py-3 text-slate-600 dark:text-slate-300">
                            <p class="line-clamp-2">{{ $post->caption ?? '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <div class="flex flex-col items-end gap-0.5 text-xs text-slate-500 dark:text-slate-400">
                                @if ($post->likes_count !== null)
                                    <span>{{ number_format($post->likes_count) }} ❤</span>
                                @endif
                                @if ($post->views_count !== null)
                                    <span>{{ number_format($post->views_count) }} 👁</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                            {{ $post->posted_at?->translatedFormat('d M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ $post->post_url }}" target="_blank" rel="noopener" class="text-xs text-slate-400 hover:text-blue-600 dark:text-slate-500 dark:hover:text-blue-400">
                                {{ __('hashtag.view_post') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                            {{ __('hashtag.no_posts_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$posts" />
@endif
