@extends('layouts.app')

@section('title', __('bulk_import.title') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('bulk_import.title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('bulk_import.subtitle') }}</p>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        @foreach (['gereja' => __('common.church'), 'personal' => __('common.personal'), 'institusi' => __('common.institution')] as $type => $label)
            <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <h2 class="mb-1 text-lg font-bold text-slate-900 dark:text-white">{{ $label }}</h2>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('bulk_import.visible_count', ['count' => $counts[$type]]) }}
                </p>

                <a
                    href="{{ route('admin.bulk-import.template', $type) }}"
                    class="mb-4 flex items-center justify-center gap-2 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    <x-icon name="arrow-down-tray" class="h-4 w-4 shrink-0" />
                    {{ __('bulk_import.download_template') }}
                </a>

                <form method="POST" action="{{ route('admin.bulk-import.import') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input
                        type="file"
                        name="file"
                        accept=".xlsx,.xls,.csv"
                        required
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200 dark:hover:file:bg-slate-700"
                    >
                    @error('file')
                        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                    >
                        <x-icon name="arrow-up-tray" class="h-4 w-4 shrink-0" />
                        {{ __('bulk_import.upload_button') }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <h2 class="mb-2 text-sm font-bold text-slate-900 dark:text-white">{{ __('bulk_import.how_title') }}</h2>
        <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-500 dark:text-slate-400">
            <li>{{ __('bulk_import.how_step_1') }}</li>
            <li>{{ __('bulk_import.how_step_2') }}</li>
            <li>{{ __('bulk_import.how_step_3') }}</li>
            <li>{{ __('bulk_import.how_step_4') }}</li>
            <li>{{ __('bulk_import.how_step_5') }}</li>
            <li>{{ __('bulk_import.how_step_6') }}</li>
        </ol>
    </div>
@endsection
