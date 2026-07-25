@extends('layouts.app')

@section('title', __('users.edit_title') . ' — ' . config('app.name'))

@section('content')
    <a
        href="{{ route('admin.users.index', ['tab' => $tab]) }}"
        class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
    >
        &larr; {{ __('common.back') }}
    </a>

    <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('users.edit_title') }}</h1>
    <p class="mb-8 text-sm text-slate-500 dark:text-slate-400">{{ $target->email }}</p>

    <form
        method="POST"
        action="{{ route('admin.users.update', $target) }}"
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="tab" value="{{ $tab }}">

        <x-form-field name="name" :label="__('users.edit_name_label')" :hint="__('users.edit_name_hint')" required :value="$target->name" />

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.users.index', ['tab' => $tab]) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>
@endsection
