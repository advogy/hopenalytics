@php
    $subjectTypes = [
        'Union' => __('common.union'),
        'Conference' => __('common.conference'),
        'Church' => __('common.church'),
        'Institution' => __('common.institution'),
        'Person' => __('common.personal'),
        'User' => __('audit.type_user'),
    ];
@endphp

@extends('layouts.app')

@section('title', __('audit.title') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('audit.title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('audit.subtitle') }}</p>
    </div>

    <div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <h2 class="mb-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('common.filter') }}</h2>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <label class="relative w-full sm:w-auto">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ __('audit.search_placeholder') }}"
                    class="w-full sm:w-72 rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>

            <label class="relative">
                <select
                    name="subject_type"
                    onchange="this.form.submit()"
                    class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-4 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    <option value="">{{ __('audit.filter_all_types') }}</option>
                    @foreach ($subjectTypes as $value => $label)
                        <option value="{{ $value }}" @selected($subjectType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            </label>

            <button type="submit" class="rounded-full bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ __('common.search') }}
            </button>

            @if ($search || $subjectType)
                <a href="{{ route('admin.audit-log.index') }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                    {{ __('common.reset_filter') }}
                </a>
            @endif
        </form>
    </div>

    <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
        @if ($logs->isEmpty())
            <div class="p-12 text-center">
                <p class="text-slate-500 dark:text-slate-400">
                    {{ ($search || $subjectType) ? __('audit.no_match') : __('audit.no_logs_yet') }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('audit.col_time') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('audit.col_actor') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('audit.col_description') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('audit.col_subject') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($logs as $log)
                            <tr class="align-top">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">
                                    {{ $log->created_at->translatedFormat('d M Y H:i') }}
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    {{ $log->actor_name ?? __('audit.system') }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $log->description }}
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                    @if ($log->subject_type)
                                        {{ $subjectTypes[$log->subject_type] ?? $log->subject_type }}
                                        @if ($log->subject_label)
                                            <span class="text-slate-400 dark:text-slate-500">— {{ $log->subject_label }}</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 pb-4">
                <x-pagination :paginator="$logs" />
            </div>
        @endif
    </div>
@endsection
