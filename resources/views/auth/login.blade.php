@extends('layouts.guest')

@section('title', __('auth.login_title') . ' — ' . config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ __('auth.login_title') }}
    </h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        {{ __('auth.login_subtitle') }}
    </p>

    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf

        <x-form-field name="email" type="email" :label="__('auth.email')" required :value="old('email')" />
        <x-form-field name="password" type="password" :label="__('auth.password')" required />

        <p class="mb-5 -mt-3 text-right text-sm">
            <a href="{{ route('forgot-password') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                {{ __('auth.forgot_password_link') }}
            </a>
        </p>

        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
            {{ __('auth.login_button') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('auth.no_account_yet') }}
        <a href="{{ route('register') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
            {{ __('auth.register_link') }}
        </a>
    </p>

    <div class="mt-8 flex items-center justify-center gap-4 text-sm">
        <a
            href="{{ route('churches.presentation') }}"
            target="_blank"
            class="flex items-center gap-1.5 text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
        >
            <x-icon name="arrow-top-right-on-square" class="h-4 w-4" />
            {{ __('nav.presentation') }}
        </a>

        <span class="text-slate-300 dark:text-slate-600">|</span>

        <a
            href="{{ route('about.public') }}"
            target="_blank"
            class="flex items-center gap-1.5 text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
        >
            <x-icon name="arrow-top-right-on-square" class="h-4 w-4" />
            {{ __('nav.about') }}
        </a>
    </div>
@endsection
