@php
    $days = __('settings.days');
@endphp

@extends('layouts.app')

@section('title', __('settings.title') . ' — ' . config('app.name'))

@section('content')
    <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('settings.title') }}</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">{{ __('settings.subtitle') }}</p>

    <x-tab-bar>
        <x-tab-button tab-key="general">{{ __('settings.tab_general') }}</x-tab-button>
        @can('manage-platform-visibility')
            <x-tab-button tab-key="platform">{{ __('settings.tab_platform') }}</x-tab-button>
        @endcan
        <x-tab-button tab-key="coordinator">{{ __('settings.tab_coordinator') }}</x-tab-button>
    </x-tab-bar>

    {{--
        One form, tabs are a purely visual grouping — form boundaries don't have to match tab
        boundaries, and keeping every setting in a single <form> (one submit button, one PUT) is
        simpler than juggling several independent forms/requests for what's still just one
        settings row, per the user's explicit call ("semua masih dalam satu halaman setting").
        The Union coordinator list below is the one exception: each row is its own account
        (Union), not part of this settings row, so it's its own set of forms outside this one.
    --}}
    <form method="POST" action="{{ route('settings.update') }}" class="max-w-lg space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">

        <div data-tab-panel="general" class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
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
                <x-select-field name="auto_fetch_day" :label="__('settings.day')" wrapper-class="">
                    @foreach ($days as $value => $label)
                        <option value="{{ $value }}" @selected((int) old('auto_fetch_day', $settings->auto_fetch_day) === $value)>{{ $label }}</option>
                    @endforeach
                </x-select-field>

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

        @can('manage-platform-visibility')
            <div data-tab-panel="platform" class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <h2 class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('settings.platform_title') }}</h2>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('settings.platform_subtitle') }}</p>

                <div class="space-y-3">
                    @foreach (\App\Models\AppSetting::allPlatforms() as $platform)
                        <div class="flex items-center gap-2">
                            <input
                                type="checkbox" id="{{ $platform['column'] }}" name="{{ $platform['column'] }}" value="1"
                                @checked(old($platform['column'], $settings->{$platform['column']}))
                                class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500"
                            >
                            <label for="{{ $platform['column'] }}" class="flex-1 text-sm font-medium">
                                {{ $platform['label'] }}
                            </label>
                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                {{ __('settings.platform_account_count', ['count' => $platformAccountCounts[$platform['value']] ?? 0]) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endcan

        <div data-tab-panel="coordinator" class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <h2 class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('settings.cs_title') }}</h2>
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('settings.cs_subtitle') }}</p>

            <x-form-field
                name="cs_whatsapp_number"
                :label="__('settings.cs_whatsapp_number')"
                :hint="__('settings.cs_whatsapp_number_hint')"
                :value="$settings->cs_whatsapp_number"
                placeholder="628123456789"
            />

            <div class="mb-1.5 block text-sm font-medium">{{ __('settings.cs_groups') }}</div>
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">{{ __('settings.cs_groups_hint') }}</p>
            <x-group-links-fields name="groups" :groups="$globalGroups" />

            <hr class="my-6 border-black/5 dark:border-white/5">

            <x-form-field
                name="bulk_email_delay_seconds"
                type="number"
                :label="__('settings.bulk_email_delay_seconds')"
                :hint="__('settings.bulk_email_delay_seconds_hint')"
                :value="$settings->bulk_email_delay_seconds"
                wrapper-class="mb-0"
            />
        </div>

        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
            {{ __('settings.save_settings') }}
        </button>
    </form>

    <div data-tab-panel="coordinator" class="mt-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <h2 class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('settings.union_coordinators_title') }}</h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('settings.union_coordinators_subtitle') }}</p>

        @if ($unions->isEmpty())
            <x-empty-state variant="inline">{{ __('settings.union_coordinator_none') }}</x-empty-state>
        @else
            {{--
                Grid, not a <table> — each row needs to be a real <form> (own action/CSRF/method,
                since every Union saves independently), and a <form> can't validly wrap <td>
                elements as a direct child of <tr>. class="contents" makes each form's own
                wrapper box disappear from layout entirely, so its 4 children become direct
                items of this grid, giving the same visual table without the invalid nesting.
            --}}
            <div class="grid grid-cols-1 gap-x-3 gap-y-3 text-sm md:grid-cols-[1fr_1fr_1.4fr_auto] md:items-start">
                <div class="hidden text-xs font-medium text-slate-500 dark:text-slate-400 md:block">{{ __('settings.union_col_name') }}</div>
                <div class="hidden text-xs font-medium text-slate-500 dark:text-slate-400 md:block">{{ __('settings.union_col_whatsapp') }}</div>
                <div class="hidden text-xs font-medium text-slate-500 dark:text-slate-400 md:block">{{ __('settings.union_col_group_link') }}</div>
                <div class="hidden md:block"></div>

                @foreach ($unions as $union)
                    <form method="POST" action="{{ route('admin.unions.update-coordinator', $union) }}" class="contents">
                        @csrf
                        @method('PATCH')
                        <div class="border-t border-slate-100 pt-3 font-medium md:border-t-0 md:pt-1.5 dark:border-slate-800">{{ $union->name }}</div>
                        <div>
                            <input
                                type="text"
                                name="coordinator_whatsapp_number"
                                value="{{ $union->coordinator_whatsapp_number }}"
                                placeholder="628123456789"
                                class="w-full rounded-lg border border-black/10 bg-white px-2.5 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                            >
                        </div>
                        <div>
                            <x-group-links-fields name="groups" :groups="$union->groups" />
                        </div>
                        <div class="pb-3 md:pb-0 md:pt-1">
                            <button type="submit" class="w-full rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-200 md:w-auto dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                {{ __('settings.union_coordinator_save') }}
                            </button>
                        </div>
                    </form>
                @endforeach
            </div>
        @endif
    </div>

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
