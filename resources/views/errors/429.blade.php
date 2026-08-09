@extends('layouts.guest')

@section('title', __('auth.too_many_attempts_title') . ' — ' . config('app.name'))

@section('content')
    <div class="text-center">
        <x-icon name="clock" class="mx-auto mb-4 h-10 w-10 text-amber-500" />

        <h1 class="mb-2 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
            {{ __('auth.too_many_attempts_title') }}
        </h1>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
            {{ __('auth.too_many_attempts_body') }}
        </p>

        <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
            &larr; {{ __('auth.back_to_login') }}
        </a>
    </div>
@endsection
