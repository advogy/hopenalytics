@extends('layouts.guest')

@section('title', __('auth.forgot_password_title') . ' — ' . config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ __('auth.forgot_password_title') }}
    </h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        {{ __('auth.forgot_password_subtitle') }}
    </p>

    <form method="POST" action="{{ route('forgot-password.send') }}" data-disable-on-submit>
        @csrf

        <x-form-field name="email" type="email" :label="__('auth.email')" required :value="old('email')" />

        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
            {{ __('auth.forgot_password_button') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
            {{ __('auth.back_to_login') }}
        </a>
    </p>
@endsection
