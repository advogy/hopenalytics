@extends('layouts.guest')

@section('title', __('auth.verify_otp_title') . ' — ' . config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ __('auth.verify_otp_title') }}
    </h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        {{ __('auth.verify_otp_subtitle', ['email' => $email]) }}
    </p>

    <form method="POST" action="{{ route('verify-otp.attempt') }}">
        @csrf

        <x-form-field
            name="code"
            :label="__('auth.otp_code')"
            required
            autocomplete="one-time-code"
            inputmode="numeric"
            maxlength="6"
            class="text-center text-lg tracking-[0.5em]"
        />

        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
            {{ __('auth.verify_otp_button') }}
        </button>
    </form>

    <form method="POST" action="{{ route('verify-otp.resend') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
            {{ __('auth.resend_otp') }}
        </button>
    </form>
@endsection
