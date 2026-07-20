@extends('layouts.guest')

@section('title', 'Lengkapi Profil — ' . config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
        Lengkapi Profil
    </h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Beri tahu kami Uni, Daerah, dan Gereja Anda saat ini, supaya admin di wilayah Anda bisa menemukan Anda. Langkah ini bisa dilewati dan dilengkapi kapan saja lewat Profil Saya.
    </p>

    <form method="POST" action="{{ route('profile.complete.store') }}" data-disable-on-submit>
        @csrf

        @include('partials.region-fields', ['unions' => $unions, 'conferences' => $conferences, 'churches' => $churches])

        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
            Lengkapi Profil
        </button>
    </form>

    <form method="POST" action="{{ route('profile.complete.skip') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-slate-500 hover:underline dark:text-slate-400">
            Lewati langkah ini
        </button>
    </form>
@endsection
