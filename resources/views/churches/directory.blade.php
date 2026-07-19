@php
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
@endphp

@extends('layouts.app')

@section('title', __('nav.directory') . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('churches.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back_to_dashboard') }}
    </a>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.directory') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('directory.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a
                href="{{ route('churches.create') }}"
                data-tab-panel="gereja"
                class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
            >
                {{ __('directory.add_church') }}
            </a>
            <a
                href="{{ route('people.create') }}"
                data-tab-panel="personal"
                class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
            >
                {{ __('directory.add_personal') }}
            </a>
        </div>
    </div>

    <div class="mb-6 flex gap-2 border-b border-black/5 dark:border-white/5">
        <button type="button" data-tab-button="gereja" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('common.church') }}
        </button>
        <button type="button" data-tab-button="personal" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('common.personal') }}
        </button>
    </div>

    <div class="mb-6 flex justify-end">
        <div data-tab-panel="gereja">
            <x-export-button :url="route('export.directory.preview', array_filter(['platform' => $selectedPlatform, 'type' => 'gereja']))" />
        </div>
        <div data-tab-panel="personal">
            <x-export-button :url="route('export.directory.preview', array_filter(['platform' => $selectedPlatform, 'type' => 'personal']))" />
        </div>
    </div>

    <div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <h2 class="mb-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('common.filter') }}</h2>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">

            <label class="relative">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ __('directory.search_placeholder') }}"
                    class="w-56 rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>

            <label class="relative">
                <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <select
                    name="platform"
                    onchange="this.form.submit()"
                    class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    <option value="">{{ __('common.all_social_media') }}</option>
                    @foreach ($platformLabels as $value => $label)
                        <option value="{{ $value }}" @selected($selectedPlatform === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            </label>

            <label class="relative">
                <x-icon name="arrow-path" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <select
                    name="auto_fetch"
                    onchange="this.form.submit()"
                    class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    <option value="">{{ __('directory.auto_fetch_all') }}</option>
                    <option value="auto" @selected($autoFetch === 'auto')>{{ __('directory.auto_fetch_auto') }}</option>
                    <option value="manual" @selected($autoFetch === 'manual')>{{ __('directory.auto_fetch_manual') }}</option>
                </select>
                <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            </label>

            <button type="submit" class="rounded-full bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ __('common.search') }}
            </button>

            @if ($selectedPlatform || $search || $autoFetch)
                <a href="{{ route('churches.directory', ['tab' => $activeTab]) }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                    {{ __('common.reset_filter') }}
                </a>
            @endif
        </form>
    </div>

    {{-- ===================== TAB: GEREJA ===================== --}}
    <div data-tab-panel="gereja">
        @if ($churches->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
                <p class="text-slate-500 dark:text-slate-400">{{ __('directory.no_churches') }}</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.church') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('directory.church_accounts') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('directory.general_accounts') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        @foreach ($churches as $church)
                            @php $socialsByCategory = $church->socials->groupBy(fn ($s) => $s->category->value); @endphp
                            <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-violet-600 text-xs font-bold text-white">
                                            {{ mb_substr($church->name, 0, 1) }}
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ route('churches.show', $church) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400">
                                                {{ $church->name }}
                                            </a>
                                            @if ($church->city)
                                                <p class="text-xs text-slate-400 dark:text-slate-500">{{ $church->city }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                @foreach (['gereja', 'umum'] as $category)
                                    <td class="px-4 py-3">
                                        <div class="space-y-1.5">
                                            @forelse ($socialsByCategory->get($category, collect()) as $social)
                                                <a href="{{ route('socials.edit', $social) }}" class="flex items-center gap-2 hover:text-blue-600 dark:hover:text-blue-400">
                                                    <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                    <span>{{ $social->display_handle }}</span>
                                                    @unless ($social->is_auto_fetch)
                                                        <x-icon name="pencil-square" class="h-3.5 w-3.5 shrink-0 text-amber-500" title="{{ __('directory.manual_badge_title') }}" />
                                                    @endunless
                                                </a>
                                            @empty
                                                <span class="text-slate-300 dark:text-slate-600">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-right">
                                    <a
                                        href="{{ route('socials.create', $church) }}"
                                        title="{{ __('directory.add_account') }}"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-blue-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-400"
                                    >
                                        <x-icon name="plus" class="h-3.5 w-3.5" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$churches" />
        @endif
    </div>

    {{-- ===================== TAB: PERSONAL ===================== --}}
    <div data-tab-panel="personal">
        @if ($people->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
                <p class="text-slate-500 dark:text-slate-400">{{ __('directory.no_people') }}</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.name') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        @foreach ($people as $person)
                            <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-violet-600 text-xs font-bold text-white">
                                            {{ mb_substr($person->name, 0, 1) }}
                                        </span>
                                        <a href="{{ route('people.show', $person) }}" class="min-w-0 font-medium hover:text-blue-600 dark:hover:text-blue-400">
                                            {{ $person->name }}
                                        </a>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="space-y-1.5">
                                        @forelse ($person->socials as $social)
                                            <a href="{{ route('socials.edit', $social) }}" class="flex items-center gap-2 hover:text-blue-600 dark:hover:text-blue-400">
                                                <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                <span>{{ $social->display_handle }}</span>
                                                @unless ($social->is_auto_fetch)
                                                    <x-icon name="pencil-square" class="h-3.5 w-3.5 shrink-0 text-amber-500" title="{{ __('directory.manual_badge_title') }}" />
                                                @endunless
                                            </a>
                                        @empty
                                            <span class="text-slate-300 dark:text-slate-600">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a
                                        href="{{ route('people.socials.create', $person) }}"
                                        title="{{ __('directory.add_account') }}"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-blue-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-400"
                                    >
                                        <x-icon name="plus" class="h-3.5 w-3.5" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$people" />
        @endif
    </div>

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
