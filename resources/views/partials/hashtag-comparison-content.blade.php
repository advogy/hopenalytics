{{--
    Shared hashtag-comparison content — used both by the standalone Perbandingan Hastag page
    (churches/hashtag-comparison.blade.php) and the "Hastag" tab embedded in Analitik & Grafik
    (churches/analytics.blade.php), since the underlying data is identical everywhere — hashtag
    posts have no owner, so the same global data applies regardless of scope (see
    ChurchDashboardController::hashtagComparisonData()).

    Expected: $hashtags, $platforms, $lastUpdatedAt, $rows, $grandTotalByPlatform, $grandTotal,
    $posts (paginator, each row's churchSocial relation eager-loaded), $selectedHashtagId,
    $selectedPlatform, $isNasionalView, $isUniView, $unionOptions, $conferenceOptions,
    $selectedUnionId, $selectedConferenceId.
    Optional: $formAction (omit for a self-submitting form on the current URL), $clearUrl (passed
    to x-filter-card), $platformParam (default 'platform' — override to avoid colliding with a
    host page's own "platform" query param, e.g. the Hastag tab on Analitik & Grafik),
    $hiddenFields (assoc array of extra <input type="hidden"> name => value, e.g. ['tab' =>
    'hastag'] so the filter form's GET submit stays on the right tab), $hideExportButton (default
    false — the Hastag tab on Analitik & Grafik sets this true and renders its own export button
    in the same row as its title instead, matching every other tab's title-row layout; the
    standalone Perbandingan Hastag pages leave this off and keep getting the button from here).
--}}
@php
    $hideExportButton = $hideExportButton ?? false;
    $platformParam = $platformParam ?? 'platform';
    $platformLabelsHashtag = \App\Models\AppSetting::current()->enabledPlatformLabels();
    $hashtagFilterFormId = 'hashtag-filter-form-'.($hiddenFields['tab'] ?? 'standalone');
    // Built from the already-resolved $selected* variables, not the raw query string — the
    // embedded Hastag tab reads its platform from "hashtag_platform" (see $platformParam above)
    // while the standalone Perbandingan Hastag pages read plain "platform"; the export route
    // only ever needs to know the resolved value, not which field name it arrived under.
    $hashtagExportParams = array_filter([
        'hashtag' => $selectedHashtagId,
        'platform' => $selectedPlatform,
        'union_id' => $selectedUnionId,
        'conference_id' => $selectedConferenceId,
    ]);
@endphp

@if (! $hideExportButton)
    @can('browse-directory-analytics')
        <div class="mb-4 flex justify-end">
            <x-export-button :url="route('export.hashtag.preview', $hashtagExportParams)" />
        </div>
    @endcan
@endif

@if ($hashtags->isEmpty())
    <x-empty-state>
        {{ __('hashtag.no_hashtags_tracked') }}
        @can('manage-settings')
            <a href="{{ route('admin.hashtags.index') }}" class="text-blue-600 hover:underline dark:text-blue-400">{{ __('nav.hashtags') }} →</a>
        @endcan
    </x-empty-state>
@else
    {{-- Filter comes first, then the summary table it actually affects — per the user's explicit
         call, so the filter reads as "narrow what's below" rather than an afterthought bolted
         under a table that already rendered unfiltered. --}}
    <x-filter-card :clear-url="($selectedHashtagId || $selectedPlatform || $selectedUnionId || $selectedConferenceId || $selectedPostedFrom || $selectedPostedTo) ? ($clearUrl ?? null) : null">
        <form id="{{ $hashtagFilterFormId }}" method="GET" @if (! empty($formAction)) action="{{ $formAction }}" @endif class="flex flex-wrap items-center gap-3">
            @foreach ($hiddenFields ?? [] as $hiddenName => $hiddenValue)
                <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
            @endforeach

            {{-- Same order as every other Data Per * tab's own filter card (see e.g. the
                 Organisasi tab above): Uni/Daerah region first, then this tab's own "entity"
                 picker (hashtag, here), then platform, then the date/time range last. --}}
            @include('partials.analytics-region-filter', [
                'prefix' => 'hashtag',
                'formId' => $hashtagFilterFormId,
                'isNasionalView' => $isNasionalView,
                'isUniView' => $isUniView,
                'unionOptions' => $unionOptions,
                'conferenceOptions' => $conferenceOptions,
                'selectedUnionId' => $selectedUnionId,
                'selectedConferenceId' => $selectedConferenceId,
            ])

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
                    {{-- Same shared label as every other platform filter on the app (Analitik &
                         Grafik's other tabs, Direktori) — this one used to say "Semua Platform"
                         on its own, the only place in the app that did. --}}
                    <option value="">{{ __('common.all_social_media') }}</option>
                    @foreach ($platforms as $platform)
                        <option value="{{ $platform }}" @selected($selectedPlatform === $platform)>{{ $platformLabelsHashtag[$platform] }}</option>
                    @endforeach
                </select>
                <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            </label>

            {{-- Optional monitoring window — e.g. watching a coordinated hashtag launch hour by
                 hour. Both fields must be filled for it to take effect (see
                 hashtagComparisonData()); leaving either blank falls straight back to the normal
                 unbounded, per-date view. Same pill-with-icon shape as
                 partials/analytics-date-range-filter.blade.php's own date range, but with time
                 precision (that one is deliberately date-only — its own per-week data has no
                 finer granularity to filter by) and no preset buttons, since "last 30 days"-style
                 presets don't mean anything for an hour-precision monitoring window. --}}
            <div class="flex items-center gap-1.5 rounded-full border border-black/10 bg-slate-50 py-2 pr-3 pl-9 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700 relative" title="{{ __('hashtag.posted_range_hint') }}">
                <x-icon name="clock" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="datetime-local"
                    name="posted_from"
                    value="{{ $selectedPostedFrom }}"
                    onchange="this.form.submit()"
                    aria-label="{{ __('hashtag.posted_from') }}"
                    class="border-0 bg-transparent p-0 text-sm font-medium text-slate-700 focus:ring-0 dark:text-slate-200"
                >
                <span class="text-sm text-slate-400">–</span>
                <input
                    type="datetime-local"
                    name="posted_to"
                    value="{{ $selectedPostedTo }}"
                    onchange="this.form.submit()"
                    aria-label="{{ __('hashtag.posted_to') }}"
                    class="border-0 bg-transparent p-0 text-sm font-medium text-slate-700 focus:ring-0 dark:text-slate-200"
                >
            </div>
        </form>
    </x-filter-card>

    @if ($selectedPostedFrom && ! $selectedPostedTo || $selectedPostedTo && ! $selectedPostedFrom)
        <p class="mb-6 text-sm text-amber-600 dark:text-amber-400">{{ __('hashtag.posted_range_incomplete') }}</p>
    @endif

    <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
        {{ $lastUpdatedAt ? __('dashboard.last_updated_at', ['time' => $lastUpdatedAt->translatedFormat('d M Y H:i')]) : __('dashboard.last_updated_never') }}
    </p>

    {{-- Per the user's explicit call — this table's own "Total Sepanjang Waktu" reads easily as
         "how many posts right now" at a glance, when it's actually an ever-growing cumulative
         count since tracking began (see MatchAccountHashtags — a HashtagPost row is never
         deleted once matched, even after the real post is gone or ages out of an account's
         recent-posts sample). Spelling that out here once, rather than everywhere the number
         itself appears, keeps every occurrence readable on its own. --}}
    <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('hashtag.summary_table_title') }}</p>
    <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">{{ __('hashtag.summary_table_subtitle') }}</p>

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

    {{-- Full-width per the user's explicit call — one wide chart rather than folded into a
         narrower grid alongside something else. --}}
    <div class="mb-8 w-full rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="font-bold text-slate-900 dark:text-white">
            {{ $isMonitoringWindow ? __('hashtag.growth_chart_title_hourly') : __('hashtag.growth_chart_title') }}
        </p>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
            {{ $isMonitoringWindow ? __('hashtag.growth_chart_subtitle_hourly') : __('hashtag.growth_chart_subtitle') }}
        </p>
        <x-growth-chart :values="$growthValues" :labels="$growthLabels" :short-labels="$growthShortLabels" :date-keys="$growthDateKeys" :width="960" :height="180" label-density="dense" />
    </div>

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
