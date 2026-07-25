@php
    // Union/Conference ("Uni Saya"/"Daerah Saya") go back to Analitik & Statistik instead of
    // $backRoute (Kelola Akun) for consistency with churches.show ("Gereja Saya") — all
    // three "Saya" pages point back to the same place. Institution has no "Saya" nav shortcut
    // (its own tab isn't hidden from admin_institusi, unlike Uni/Daerah), so it keeps going
    // back to Kelola Akun as before.
    $isOrgLevel = $owner instanceof \App\Models\Union || $owner instanceof \App\Models\Conference;
@endphp

@extends('layouts.app')

@section('title', $owner->name . ' — ' . config('app.name'))

@section('content')
    @if ($isOrgLevel)
        @can('view-analytics')
            <a href="{{ route('churches.analytics', ['tab' => 'gereja']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                &larr; {{ __('nav.back_to_analytics') }}
            </a>
        @endcan
    @else
        <a href="{{ route(...$backRoute) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
            &larr; {{ __('common.back') }}
        </a>
    @endif

    <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $owner->name }}</h1>

        <a
            href="{{ route(...$createRoute) }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
        >
            {{ __('entity.add_account') }}
        </a>
    </div>

    <x-manage-accounts-list :socials="$owner->socials" />
@endsection
