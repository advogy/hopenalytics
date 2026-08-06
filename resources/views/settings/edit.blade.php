@php
    $days = __('settings.days');
@endphp

@extends('layouts.app')

@section('title', __('settings.title') . ' — ' . config('app.name'))

@section('content')
    <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('settings.title') }}</h1>
    <p class="mb-8 text-sm text-slate-500 dark:text-slate-400">{{ __('settings.subtitle') }}</p>

    {{--
        One form, two visually separate cards — form boundaries don't have to match card
        boundaries, and keeping everything in a single <form> (one submit button, one PUT) is
        simpler than juggling two independent forms/requests for what's still just one
        settings row, per the user's explicit call ("semua masih dalam satu halaman setting").
    --}}
    <form method="POST" action="{{ route('settings.update') }}" class="max-w-lg space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <h2 class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('settings.apify_title') }}</h2>
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('settings.apify_subtitle') }}</p>

            <x-form-field
                name="apify_token"
                type="password"
                :label="__('settings.apify_token')"
                :hint="$settings->apify_token ? __('settings.apify_token_hint_set') : __('settings.apify_token_hint_unset')"
                placeholder="apify_api_…"
            />

            <x-form-field
                name="youtube_api_key"
                type="password"
                :label="__('settings.youtube_api_key')"
                :hint="$settings->youtube_api_key ? __('settings.youtube_api_key_hint_set') : __('settings.youtube_api_key_hint_unset')"
                placeholder="AIza…"
            />

            <div class="mb-5 flex items-center gap-2">
                <input
                    type="checkbox" id="apify_fallback_to_manual" name="apify_fallback_to_manual" value="1"
                    @checked(old('apify_fallback_to_manual', $settings->apify_fallback_to_manual))
                    class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500"
                >
                <label for="apify_fallback_to_manual" class="text-sm font-medium">
                    {{ __('settings.apify_fallback_to_manual') }}
                </label>
            </div>

            <div class="mb-5 flex items-center gap-2">
                <input
                    type="checkbox" id="auto_fetch_enabled" name="auto_fetch_enabled" value="1"
                    @checked(old('auto_fetch_enabled', $settings->auto_fetch_enabled))
                    class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500"
                >
                <label for="auto_fetch_enabled" class="text-sm font-medium">
                    {{ __('settings.auto_fetch_active') }}
                </label>
            </div>

            <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="auto_fetch_day" class="mb-1.5 block text-sm font-medium">{{ __('settings.day') }}</label>
                    <select id="auto_fetch_day" name="auto_fetch_day" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800">
                        @foreach ($days as $value => $label)
                            <option value="{{ $value }}" @selected((int) old('auto_fetch_day', $settings->auto_fetch_day) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('auto_fetch_day')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="auto_fetch_time" class="mb-1.5 block text-sm font-medium">{{ __('settings.time_wib') }}</label>
                    <input
                        type="time" id="auto_fetch_time" name="auto_fetch_time"
                        value="{{ old('auto_fetch_time', $settings->auto_fetch_time) }}"
                        class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                    >
                    @error('auto_fetch_time')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            @if ($nextRun)
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('settings.next_fetch') }} <span class="font-medium text-slate-700 dark:text-slate-300">{{ $nextRun->translatedFormat('l, d M Y H:i') }} WIB</span>
                </p>
            @else
                <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('settings.auto_fetch_inactive') }}</p>
            @endif

            <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
                {!! __('settings.schedule_note', ['command' => '<code class="rounded bg-slate-100 px-1 py-0.5 dark:bg-slate-800">church-stats:fetch-all</code>']) !!}
            </p>
        </div>

        <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <h2 class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('settings.cs_title') }}</h2>
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('settings.cs_subtitle') }}</p>

            <x-form-field
                name="cs_whatsapp_number"
                :label="__('settings.cs_whatsapp_number')"
                :hint="__('settings.cs_whatsapp_number_hint')"
                :value="$settings->cs_whatsapp_number"
                placeholder="628123456789"
            />

            <x-form-field
                name="cs_whatsapp_group_link"
                :label="__('settings.cs_whatsapp_group_link')"
                :hint="__('settings.cs_whatsapp_group_link_hint')"
                :value="$settings->cs_whatsapp_group_link"
                placeholder="https://chat.whatsapp.com/…"
                wrapper-class=""
            />
        </div>

        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
            {{ __('settings.save_settings') }}
        </button>
    </form>
@endsection
