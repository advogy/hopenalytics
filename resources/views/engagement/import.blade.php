{{-- Division admins upload each church's membership as of a date (MembershipImportController). --}}
@extends('layouts.app')

@section('title', __('engagement.import_title') . ' — ' . config('app.name'))

@section('content')
    <x-hub-header hub="goals" />

    <div class="mb-6">
        <h2 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('engagement.import_title') }}</h2>
        <p class="max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('engagement.import_subtitle') }}</p>
    </div>

    <div class="mb-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <a
                href="{{ route('engagement.import.template') }}"
                class="mb-2 flex items-center justify-center gap-2 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                <x-icon name="arrow-down-tray" class="h-4 w-4 shrink-0" />
                {{ __('engagement.import_download') }}
            </a>
            <p class="mb-6 text-xs text-slate-400">{{ __('engagement.import_download_hint') }}</p>

            <form method="POST" action="{{ route('engagement.import.store') }}" enctype="multipart/form-data" data-disable-on-submit class="space-y-4">
                @csrf

                <div>
                    <label for="as_of" class="mb-1.5 block text-sm font-medium">{{ __('engagement.import_as_of') }}</label>
                    <input
                        type="date" id="as_of" name="as_of" required
                        value="{{ old('as_of', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
                        class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                    >
                    @error('as_of')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="file" class="mb-1.5 block text-sm font-medium">{{ __('engagement.import_file') }}</label>
                    <input
                        type="file" id="file" name="file" accept=".xlsx,.xls,.csv" required
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200 dark:hover:file:bg-slate-700"
                    >
                    @error('file')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                    <x-icon name="arrow-up-tray" class="h-4 w-4 shrink-0" />
                    {{ __('engagement.import_button') }}
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-bold text-slate-900 dark:text-white">{{ __('engagement.import_how_title') }}</h2>
            <ol class="list-decimal space-y-2 pl-5 text-sm text-slate-500 dark:text-slate-400">
                @foreach ([1, 2, 3, 4] as $step)
                    <li>{{ __('engagement.import_how_'.$step) }}</li>
                @endforeach
            </ol>
        </div>
    </div>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('engagement.import_history') }}</h2>
        @if ($uploads->isNotEmpty())
            {{-- Every figure per church, not just these per-date totals. --}}
            <x-export-button :url="route('export.membership.preview')">{{ __('engagement.export_membership_title') }}</x-export-button>
        @endif
    </div>

    @if ($uploads->isEmpty())
        <x-empty-state>{{ __('engagement.import_history_empty') }}</x-empty-state>
    @else
        <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.col_as_of') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.col_churches') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.col_members') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.status_pending') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.status_confirmed') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.status_rejected') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                    @foreach ($uploads as $upload)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ \Illuminate\Support\Carbon::parse($upload->as_of)->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($upload->churches) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($upload->members) }}</td>
                            <td @class(['px-4 py-3 text-right tabular-nums', 'font-semibold text-amber-600 dark:text-amber-400' => $upload->pending > 0])>{{ number_format($upload->pending) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-emerald-600 dark:text-emerald-400">{{ number_format($upload->confirmed) }}</td>
                            <td @class(['px-4 py-3 text-right tabular-nums', 'font-semibold text-red-600 dark:text-red-400' => $upload->rejected > 0])>{{ number_format($upload->rejected) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
