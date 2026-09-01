@extends('layouts.app')

@section('title', __('entity.verify_new_email_title') . ' — ' . config('app.name'))

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('entity.verify_new_email_title') }}</h1>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
            {{ __('auth.reset_password_subtitle', ['email' => $pendingEmail]) }}
        </p>

        <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <form method="POST" action="{{ route('profile.verify-email.attempt') }}">
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

            <form method="POST" action="{{ route('profile.verify-email.resend') }}" class="mt-4 text-center">
                @csrf
                <button type="submit" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                    {{ __('auth.resend_otp') }}
                </button>
            </form>
        </div>

        <form method="POST" action="{{ route('profile.verify-email.cancel') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                {{ __('entity.cancel_email_verification') }}
            </button>
        </form>
    </div>
@endsection
