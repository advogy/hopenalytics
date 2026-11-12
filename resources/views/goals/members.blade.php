{{-- Tujuan → "Anggota & Target" (MembershipTargetController): recorded membership and its target. --}}
@extends('layouts.app')

@section('title', __('goals.tab_members') . ' — ' . __('goals.page_title') . ' — ' . config('app.name'))

@php
    $fieldClass = 'w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800';
    $num = fn ($value) => $value === null ? '—' : number_format($value);
    $isChurchList = $group === 'gereja';
    $nameLabel = ['gereja' => __('engagement.col_church'), 'daerah' => __('engagement.col_conference'), 'uni' => __('engagement.col_union')][$group];
    $exportParams = array_filter(request()->only(['periode', 'ringkas', 'uni', 'daerah', 'cari', 'urut', 'arah']));
@endphp

@section('content')
    <x-back-link :href="route('churches.index')">{{ __('common.back_to_dashboard') }}</x-back-link>

    <div class="mb-8">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('goals.page_title') }}</h1>
        <p class="max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('goals.page_subtitle') }}</p>
    </div>

    @include('goals.partials.tabs')

    @if (! $period)
        <x-empty-state>{{ __('engagement.no_period') }}</x-empty-state>
    @else
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <p class="max-w-3xl text-sm text-slate-500 dark:text-slate-400">
                {{ __('goals.members_subtitle', ['percent' => $period->target_percent + 0, 'date' => $period->ends_on->translatedFormat('d M Y')]) }}
            </p>
            @if ($rows->isNotEmpty())
                <x-export-button :url="route('export.membership-target.preview', $exportParams)" class="shrink-0" />
            @endif
        </div>

        <form method="GET" action="{{ route('goals.members') }}" class="mb-4 grid grid-cols-1 gap-3 rounded-2xl border border-black/5 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6 lg:items-end dark:border-white/5 dark:bg-slate-900">
            @foreach (['urut', 'arah'] as $keep)
                @if (request()->filled($keep))
                    <input type="hidden" name="{{ $keep }}" value="{{ request()->query($keep) }}">
                @endif
            @endforeach

            <div>
                <label for="periode" class="mb-1.5 block text-sm font-medium">{{ __('engagement.period') }}</label>
                <select id="periode" name="periode" class="{{ $fieldClass }}">
                    @foreach ($periods as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $period->id)>{{ $option->name }} ({{ $option->target_percent + 0 }}%)</option>
                    @endforeach
                </select>
            </div>

            @if (count($groups) > 1)
                <div>
                    <label for="ringkas" class="mb-1.5 block text-sm font-medium">{{ __('goals.members_group') }}</label>
                    <select id="ringkas" name="ringkas" class="{{ $fieldClass }}">
                        @foreach ($groups as $option)
                            <option value="{{ $option }}" @selected($option === $group)>{{ __('goals.members_group_'.$option) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($unions->count() > 1)
                <div>
                    <label for="uni" class="mb-1.5 block text-sm font-medium">{{ __('engagement.col_union') }}</label>
                    <select id="uni" name="uni" class="{{ $fieldClass }}">
                        <option value="">{{ __('engagement.all_unions') }}</option>
                        @foreach ($unions as $union)
                            <option value="{{ $union->id }}" @selected($unionId === $union->id)>{{ $union->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($conferences->count() > 1)
                <div>
                    <label for="daerah" class="mb-1.5 block text-sm font-medium">{{ __('engagement.col_conference') }}</label>
                    <select id="daerah" name="daerah" class="{{ $fieldClass }}">
                        <option value="">{{ __('goals.members_all_conferences') }}</option>
                        @foreach ($conferences->when($unionId, fn ($c) => $c->where('union_id', $unionId)) as $conference)
                            <option value="{{ $conference->id }}" @selected($conferenceId === $conference->id)>{{ $conference->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="cari" class="mb-1.5 block text-sm font-medium">{{ __('goals.members_search') }}</label>
                <input type="search" id="cari" name="cari" value="{{ $search }}" placeholder="{{ __('goals.members_search_placeholder') }}" class="{{ $fieldClass }}">
            </div>

            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ __('engagement.show') }}
            </button>
        </form>

        @if ($rows->isEmpty())
            <x-empty-state>{{ __('engagement.empty') }}</x-empty-state>
        @else
            @php
                $totalCells = function () use ($totals, $num, $isChurchList, $group) {
                    return [
                        __('export.total_row_label_count', ['count' => number_format($totals['rows'])]),
                        $group === 'daerah' ? '' : null,
                        $isChurchList ? '' : null,
                        $isChurchList ? null : __('goals.members_filled', ['filled' => number_format($totals['filled']), 'total' => number_format($totals['churches'])]),
                        $num($totals['members']),
                        '',
                        $num($totals['target']),
                    ];
                };
            @endphp

            <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <x-sortable-th column="name" :sort="$sort" :direction="$direction">{{ $nameLabel }}</x-sortable-th>
                            @if ($group === 'daerah')
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.col_union') }}</th>
                            @endif
                            @if ($isChurchList)
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.col_conference') }} · {{ __('engagement.col_union') }}</th>
                            @else
                                <x-sortable-th column="churches" :sort="$sort" :direction="$direction" align="right">{{ __('engagement.col_churches_with_data') }}</x-sortable-th>
                            @endif
                            <x-sortable-th column="members" :sort="$sort" :direction="$direction" align="right">{{ __('engagement.col_members') }}</x-sortable-th>
                            <x-sortable-th column="as_of" :sort="$sort" :direction="$direction" align="right">{{ $isChurchList ? __('engagement.col_as_of') : __('goals.members_latest_as_of') }}</x-sortable-th>
                            <x-sortable-th column="target" :sort="$sort" :direction="$direction" align="right">{{ __('goals.members_target_col', ['percent' => $period->target_percent + 0]) }}</x-sortable-th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        {{-- Totals at the top as well as the bottom: the list can be long. --}}
                        <tr class="border-b-2 border-slate-200 bg-slate-50 font-semibold dark:border-slate-700 dark:bg-slate-800/60">
                            @foreach (array_values(array_filter($totalCells(), fn ($cell) => $cell !== null)) as $i => $cell)
                                <td @class(['px-4 py-3 tabular-nums', 'text-right' => $i > ($isChurchList ? 1 : ($group === 'daerah' ? 1 : 0))])>{{ $cell }}</td>
                            @endforeach
                        </tr>
                        @foreach ($paginator as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium">
                                    @if ($row['url'])
                                        <a href="{{ $row['url'] }}" class="hover:text-blue-600 dark:hover:text-blue-400">{{ $row['name'] }}</a>
                                    @else
                                        {{ $row['name'] }}
                                    @endif
                                </td>
                                @if ($group === 'daerah')
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $row['union'] ?? '—' }}</td>
                                @endif
                                @if ($isChurchList)
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $row['conference'] ?? '—' }} · {{ $row['union'] ?? '—' }}</td>
                                @else
                                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['filled']) }} / {{ number_format($row['churches']) }}</td>
                                @endif
                                <td class="px-4 py-3 text-right tabular-nums">
                                    @if ($row['members'] === null)
                                        <span class="text-xs text-amber-600 dark:text-amber-400">{{ __('engagement.not_filled') }}</span>
                                    @else
                                        {{ number_format($row['members']) }}
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-slate-500 dark:text-slate-400">
                                    {{ $row['as_of'] ? \Illuminate\Support\Carbon::parse($row['as_of'])->translatedFormat('d M Y') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $num($row['target']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-semibold dark:border-slate-700 dark:bg-slate-800/60">
                        <tr>
                            @foreach (array_values(array_filter($totalCells(), fn ($cell) => $cell !== null)) as $i => $cell)
                                <td @class(['px-4 py-3 tabular-nums', 'text-right' => $i > ($isChurchList ? 1 : ($group === 'daerah' ? 1 : 0))])>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>

            <x-pagination :paginator="$paginator" />
        @endif
    @endif
@endsection
