{{--
    New-member wizard (see CompleteProfileController): step 1 = region, step 2 = personal
    social accounts. Both are skippable; the step tabs are plain links so a member can move
    back and forth freely.
--}}
@extends('layouts.guest')

@php
    $steps = [
        1 => ['label' => __('auth.onboarding_step_profile'), 'done' => $hasRegion],
        2 => ['label' => __('auth.onboarding_step_socials'), 'done' => $socials->isNotEmpty()],
    ];
@endphp

@section('title', ($step === 1 ? __('auth.complete_profile_title') : __('auth.socials_step_title')) . ' — ' . config('app.name'))

@section('content')
    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">
        {{ __('auth.onboarding_step', ['current' => $step, 'total' => count($steps)]) }}
    </p>

    <nav class="mb-6 flex items-center gap-2 pr-12" aria-label="{{ __('auth.onboarding_step', ['current' => $step, 'total' => count($steps)]) }}">
        @foreach ($steps as $number => $info)
            @if ($number > 1)
                <span class="h-px flex-1 bg-black/10 dark:bg-white/10" aria-hidden="true"></span>
            @endif
            <a
                href="{{ route('profile.complete', ['langkah' => $number]) }}"
                @if ($number === $step) aria-current="step" @endif
                @class([
                    'flex items-center gap-1.5 whitespace-nowrap rounded-full py-1 pl-1 pr-3 text-sm font-medium transition',
                    'bg-blue-600 text-white' => $number === $step,
                    'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' => $number !== $step,
                ])
            >
                <span @class([
                    'inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold',
                    'bg-white text-blue-600' => $number === $step,
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400' => $number !== $step && $info['done'],
                    'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => $number !== $step && ! $info['done'],
                ])>
                    @if ($number !== $step && $info['done'])
                        <x-icon name="check-circle" class="h-3.5 w-3.5" />
                    @else
                        {{ $number }}
                    @endif
                </span>
                {{ $info['label'] }}
            </a>
        @endforeach
    </nav>

    @if ($step === 1)
        <h1 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
            {{ __('auth.complete_profile_title') }}
        </h1>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
            {{ __('auth.complete_profile_subtitle') }}
        </p>

        <form method="POST" action="{{ route('profile.complete.store') }}" data-disable-on-submit>
            @csrf

            @include('partials.region-fields', [
                'unions' => $unions,
                'conferences' => $conferences,
                'churches' => $churches,
                'selectedUnionId' => $selectedUnionId,
                'selectedConferenceId' => $selectedConferenceId,
                'selectedChurchName' => $selectedChurchName,
            ])

            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                {{ __('auth.complete_profile_button') }}
            </button>
        </form>

        <p class="mt-4 text-center">
            <a href="{{ route('profile.complete', ['langkah' => 2]) }}" class="text-sm font-medium text-slate-500 hover:underline dark:text-slate-400">
                {{ __('auth.complete_profile_skip') }}
            </a>
        </p>
    @else
        <h1 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
            {{ __('auth.socials_step_title') }}
        </h1>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
            {{ __('auth.socials_step_subtitle') }}
        </p>

        @if ($socials->isNotEmpty())
            <p class="mb-2 text-sm font-medium">{{ __('auth.socials_step_added') }}</p>
            <ul class="mb-6 space-y-2">
                @foreach ($socials as $social)
                    <li class="flex items-center gap-3 rounded-xl border border-black/10 px-3 py-2 dark:border-white/10">
                        <x-platform-icon :platform="$social->platform" class="h-7 w-7 text-xs" />
                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $social->display_handle }}</span>
                        <x-icon name="check-circle" class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('people.socials.store', $person) }}" data-disable-on-submit>
            @csrf
            <input type="hidden" name="from" value="onboarding">

            <x-select-field name="platform" :label="__('entity.platform')" required>
                @foreach (\App\Models\AppSetting::current()->enabledPlatformLabels() as $value => $label)
                    <option value="{{ $value }}" @selected(old('platform') === $value)>{{ $label }}</option>
                @endforeach
            </x-select-field>

            <x-form-field name="handle" :label="__('entity.handle')" required :placeholder="__('entity.handle_placeholder', ['example' => 'johndoe'])" />

            <x-form-field
                name="profile_url"
                :label="__('entity.profile_url')"
                :hint="__('entity.profile_url_hint')"
                type="url"
                placeholder="https://www.facebook.com/..."
            />

            <div class="mb-5 flex items-start gap-2">
                <input type="checkbox" id="consent" name="consent" value="1" @checked(old('consent')) required class="mt-0.5 h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500">
                <label for="consent" class="text-sm">
                    {{ __('entity.social_consent_label') }}
                    <span class="block text-xs text-slate-400">{{ __('entity.social_consent_hint') }}</span>
                </label>
            </div>
            @error('consent')
                <p class="-mt-3 mb-5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror


            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                {{ __('auth.complete_profile_button') }}
            </button>
        </form>

        <script>
            // Mirrors the server's profile_url.required_if:platform,facebook rule.
            (function () {
                var platformField = document.getElementById('platform');
                var profileUrlField = document.getElementById('profile_url');

                function syncRequired() {
                    profileUrlField.required = platformField.value === 'facebook';
                }

                platformField.addEventListener('change', syncRequired);
                syncRequired();
            })();
        </script>

        <form method="POST" action="{{ route('profile.complete.finish') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm font-medium text-slate-500 hover:underline dark:text-slate-400">
                {{ __('auth.complete_profile_skip') }}
            </button>
        </form>
    @endif
@endsection
