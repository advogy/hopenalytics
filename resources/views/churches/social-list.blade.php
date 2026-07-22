@php
    // A gereja-level admin never sees Kelola Akun's Gereja tab (manage-hierarchy excludes
    // them from every organization tab there, even though they can now reach the page itself
    // for Personal) — their own church's "list" is churches.show ("Gereja Saya"), reached
    // straight from the nav instead. Everyone else who can actually manage more than one
    // church goes back to the Gereja tab as before.
    $backRoute = auth()->user()->role?->level() === 'gereja'
        ? route('churches.show', $church)
        : route('admin.accounts.index', ['tab' => 'gereja']);
@endphp

@extends('layouts.app')

@section('title', $church->name . ' — ' . config('app.name'))

@section('content')
    <a href="{{ $backRoute }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back') }}
    </a>

    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $church->name }}</h1>
            @if ($church->city)
                <p class="text-slate-500 dark:text-slate-400">{{ $church->city }}</p>
            @endif
        </div>

        <a
            href="{{ route('socials.create', $church) }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
        >
            {{ __('entity.add_account') }}
        </a>
    </div>

    <x-manage-accounts-list :socials="$church->socials" :group-by-category="true" />
@endsection
