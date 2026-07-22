@extends('layouts.app')

@section('title', $person->name . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('admin.accounts.index', ['tab' => 'personal']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back') }}
    </a>

    <div class="mb-8 flex items-center justify-between">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $person->name }}</h1>

        <a
            href="{{ route('people.socials.create', $person) }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
        >
            {{ __('entity.add_account') }}
        </a>
    </div>

    <x-manage-accounts-list :socials="$person->socials" />
@endsection
