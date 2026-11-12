{{--
    The full Tujuan page (MemberEngagementController) — data only. Its settings (targets,
    periods, membership upload and confirmation) live in the account menu. The dashboard's
    Tujuan section is the summary of this page and links here.
--}}
@extends('layouts.app')

@section('title', __('goals.page_title') . ' — ' . config('app.name'))

@php
    $selectClass = 'w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800';
    $num = fn ($value) => $value === null ? '—' : number_format($value);
@endphp

@section('content')
    <x-back-link :href="route('churches.index')">{{ __('common.back_to_dashboard') }}</x-back-link>

    <div class="mb-8">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('goals.page_title') }}</h1>
        <p class="max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('goals.page_subtitle') }}</p>
    </div>

    @include('goals.partials.tabs')

    {{-- Metric goals --}}
    @if ($goalRows->isNotEmpty())
        <section class="mb-10">
            <div class="mb-3">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('goals.metrics_title') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $goalRows->first()['scopeLabel'] }}</p>
            </div>
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach ($goalRows as $row)
                    <x-goal-card
                        :label="$row['label']"
                        :year="$row['year']"
                        :target="$row['target']"
                        :current="$row['current']"
                        :percent="$row['percent']"
                    />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Member engagement --}}
    @if ($showEngagement)
        <section id="keterlibatan" class="scroll-mt-20">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('engagement.title') }}</h2>
                    @if ($period)
                        <p class="max-w-3xl text-sm text-slate-500 dark:text-slate-400">
                            {{ __('engagement.subtitle', ['percent' => $period->target_percent + 0]) }}
                        </p>
                    @endif
                </div>
                @if ($period && $rows->isNotEmpty())
                    {{-- Exports exactly what's shown: period, measure, drill-down and sort. --}}
                    <x-export-button :url="route('export.engagement.preview', array_filter(request()->only(['periode', 'ukuran', 'uni', 'daerah', 'urut', 'arah'])))" class="shrink-0" />
                @endif
            </div>

            @if (! $period)
                <x-empty-state>{{ __('engagement.no_period') }}</x-empty-state>
            @else
                <form method="GET" action="{{ route('goals.index') }}#keterlibatan" class="mb-4 grid grid-cols-1 gap-3 rounded-2xl border border-black/5 bg-white p-4 shadow-sm sm:grid-cols-[1fr_1fr_auto] sm:items-end dark:border-white/5 dark:bg-slate-900">
                    {{-- Keep the current drill-down while switching period or measure. --}}
                    @foreach (['uni', 'daerah', 'urut', 'arah'] as $keep)
                        @if (request()->filled($keep))
                            <input type="hidden" name="{{ $keep }}" value="{{ request()->query($keep) }}">
                        @endif
                    @endforeach

                    <div>
                        <label for="periode" class="mb-1.5 block text-sm font-medium">{{ __('engagement.period') }}</label>
                        <select id="periode" name="periode" class="{{ $selectClass }}">
                            @foreach ($periods as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $period->id)>
                                    {{ $option->name }} ({{ $option->starts_on->translatedFormat('d M Y') }} – {{ $option->ends_on->translatedFormat('d M Y') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="ukuran" class="mb-1.5 block text-sm font-medium">{{ __('engagement.measure') }}</label>
                        <select id="ukuran" name="ukuran" class="{{ $selectClass }}">
                            @foreach (\App\Support\MemberEngagement::MEASURES as $option)
                                <option value="{{ $option }}" @selected($option === $measure)>{{ __('engagement.measure_'.$option) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                        {{ __('engagement.show') }}
                    </button>

                    <p class="text-xs text-slate-400 sm:col-span-3">{{ __('engagement.measure_hint_'.$measure) }}</p>
                </form>

                @if (count($breadcrumbs) > 1)
                    <nav class="mb-3 flex flex-wrap items-center gap-1.5 text-sm" aria-label="{{ __('engagement.title') }}">
                        @foreach ($breadcrumbs as $crumb)
                            @if (! $loop->first)
                                <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">›</span>
                            @endif
                            @if ($crumb['url'] && ! $loop->last)
                                <a href="{{ $crumb['url'] }}" class="text-blue-600 hover:underline dark:text-blue-400">{{ $crumb['label'] }}</a>
                            @else
                                <span class="font-medium text-slate-700 dark:text-slate-200" @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                            @endif
                        @endforeach
                    </nav>
                @endif

                @if ($rows->isEmpty())
                    <x-empty-state>{{ __('engagement.empty') }}</x-empty-state>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800/60">
                                <tr>
                                    <x-sortable-th column="name" :sort="$sort" :direction="$direction" anchor="keterlibatan">{{ $regionLabel }}</x-sortable-th>
                                    <x-sortable-th column="members" :sort="$sort" :direction="$direction" align="right" anchor="keterlibatan">{{ __('engagement.col_members') }}</x-sortable-th>
                                    <x-sortable-th column="target" :sort="$sort" :direction="$direction" align="right" anchor="keterlibatan">{{ __('engagement.col_target') }}</x-sortable-th>
                                    <x-sortable-th column="active" :sort="$sort" :direction="$direction" align="right" anchor="keterlibatan">{{ __('engagement.col_active') }}</x-sortable-th>
                                    <x-sortable-th column="active_percent" :sort="$sort" :direction="$direction" align="right" anchor="keterlibatan">{{ __('engagement.col_active_percent') }}</x-sortable-th>
                                    <x-sortable-th column="growth" :sort="$sort" :direction="$direction" align="right" anchor="keterlibatan" :title="__('engagement.col_growth_hint')">{{ __('engagement.col_growth') }}</x-sortable-th>
                                    <x-sortable-th column="progress_percent" :sort="$sort" :direction="$direction" anchor="keterlibatan" class="min-w-48">{{ __('engagement.col_progress') }}</x-sortable-th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                                @foreach ($rows as $row)
                                    @include('goals.partials.engagement-row', ['row' => $row, 'num' => $num])
                                @endforeach
                            </tbody>
                            @if ($totalRow)
                                <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-semibold dark:border-slate-700 dark:bg-slate-800/60">
                                    @include('goals.partials.engagement-row', ['row' => $totalRow, 'num' => $num])
                                </tfoot>
                            @endif
                        </table>
                    </div>
                @endif
            @endif
        </section>
    @endif
@endsection
