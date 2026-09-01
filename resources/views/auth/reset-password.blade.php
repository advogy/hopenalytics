@extends('layouts.guest')

@section('title', __('auth.reset_password_title') . ' — ' . config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ __('auth.reset_password_title') }}
    </h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        {{ __('auth.reset_password_subtitle', ['email' => $email]) }}
    </p>

    <form method="POST" action="{{ route('reset-password.attempt') }}" data-disable-on-submit>
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
        <x-form-field name="password" type="password" :label="__('auth.new_password')" required />
        <x-form-field name="password_confirmation" type="password" :label="__('auth.new_password_confirmation')" required />

        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
            {{ __('auth.reset_password_button') }}
        </button>
    </form>

    <div class="mt-4 flex flex-col items-center gap-2">
        <form method="POST" action="{{ route('reset-password.resend') }}">
            @csrf
            <button type="submit" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                {{ __('auth.resend_otp') }}
            </button>
        </form>

        <form method="POST" action="{{ route('reset-password.cancel') }}" data-confirm="{{ __('auth.cancel_reset_confirm') }}">
            @csrf
            <button type="submit" class="text-sm text-slate-400 hover:text-red-600 dark:text-slate-500 dark:hover:text-red-400">
                {{ __('auth.cancel_reset') }}
            </button>
        </form>
    </div>
@endsection
