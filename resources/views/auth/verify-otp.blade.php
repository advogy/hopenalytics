@extends('layouts.guest')

@section('title', __('auth.verify_otp_title') . ' — ' . config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ __('auth.verify_otp_title') }}
    </h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        {{ __('auth.verify_otp_subtitle') }}
    </p>

    {{--
        One shared form for all three actions (verify/resend/cancel) — email+code are what
        identify the pending registration now, not session, so whichever button is clicked needs
        the same two fields along for the ride. The resend/cancel buttons target this form's
        email field via form="..." + formaction, and skip HTML5 required-field validation
        (formnovalidate) since neither of them needs a filled-in code field to do their job.
    --}}
    <form
        method="POST"
        action="{{ route('verify-otp.attempt') }}"
        id="verify-otp-form"
        data-disable-on-submit
        data-cancel-confirm-text="{{ __('auth.cancel_registration_confirm') }}"
    >
        @csrf

        <x-form-field name="email" type="email" :label="__('auth.email')" required :value="$email" />

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

    <div class="mt-4 flex flex-col items-center gap-2">
        <button
            type="submit"
            form="verify-otp-form"
            formaction="{{ route('verify-otp.resend') }}"
            formnovalidate
            class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
        >
            {{ __('auth.resend_otp') }}
        </button>

        <button
            type="submit"
            form="verify-otp-form"
            formaction="{{ route('verify-otp.cancel') }}"
            formnovalidate
            onclick="this.form.setAttribute('data-confirm', this.form.dataset.cancelConfirmText)"
            class="text-sm text-slate-400 hover:text-red-600 dark:text-slate-500 dark:hover:text-red-400"
        >
            {{ __('auth.cancel_registration') }}
        </button>
    </div>
@endsection
