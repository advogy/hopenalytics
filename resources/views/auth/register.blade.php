@extends('layouts.guest')

@section('title', __('auth.register_title') . ' — ' . config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ __('auth.register_title') }}
    </h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        {{ __('auth.register_subtitle') }}
    </p>

    <form method="POST" action="{{ route('register.attempt') }}" data-disable-on-submit>
        @csrf

        <x-form-field name="name" :label="__('auth.name')" required :value="old('name')" />
        <x-form-field name="email" type="email" :label="__('auth.email')" required :value="old('email')" />
        <x-form-field name="password" type="password" :label="__('auth.password')" required />
        <x-form-field name="password_confirmation" type="password" :label="__('auth.password')" required />

        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
            {{ __('auth.register_button') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('auth.already_have_account') }}
        <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
            {{ __('auth.login_link') }}
        </a>
    </p>
@endsection
