@extends('layouts.guest')

@section('title', 'Terlalu Banyak Percobaan — ' . config('app.name'))

@section('content')
    <div class="text-center">
        <x-icon name="clock" class="mx-auto mb-4 h-10 w-10 text-amber-500" />

        <h1 class="mb-2 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
            Terlalu Banyak Percobaan
        </h1>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
            Anda sudah mencoba beberapa kali dalam waktu singkat. Untuk keamanan, silakan tunggu beberapa menit sebelum mencoba lagi.
        </p>

        <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
            &larr; Kembali ke halaman masuk
        </a>
    </div>
@endsection
