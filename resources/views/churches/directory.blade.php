@php
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
@endphp

@extends('layouts.app')

@section('title', __('nav.directory') . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('churches.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back_to_dashboard') }}
    </a>

    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.directory') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('directory.subtitle') }}</p>
    </div>

    <div class="mb-6 flex gap-2 border-b border-black/5 dark:border-white/5">
        <button type="button" data-tab-button="gereja" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('common.church') }}
        </button>
        <button type="button" data-tab-button="institusi" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('common.institution') }}
        </button>
        <button type="button" data-tab-button="personal" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('common.personal') }}
        </button>
    </div>

    <div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <h2 class="mb-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('common.filter') }}</h2>
        <form id="directory-filter-form" method="GET" class="flex flex-wrap items-center gap-3">
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

            <button type="submit" class="rounded-full bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ __('common.search') }}
            </button>

            @if ($selectedPlatform || $search || $autoFetch || $hideEmptyChurches || $hideEmptyPeople || $hideEmptyInstitutions)
                <a href="{{ route('churches.directory', ['tab' => $activeTab]) }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                    {{ __('common.reset_filter') }}
                </a>
            @endif
        </form>
    </div>

    {{-- ===================== TAB: GEREJA ===================== --}}
    <div data-tab-panel="gereja">
        <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-3 px-4 py-3">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input
                        type="checkbox"
                        form="directory-filter-form"
                        name="hide_empty_churches"
                        value="1"
                        onchange="this.form.submit()"
                        @checked($hideEmptyChurches)
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                    >
                    {{ __('directory.hide_empty_churches') }}
                </label>
            </div>

            @if ($churches->isEmpty())
                <div class="border-t border-black/5 p-12 text-center dark:border-white/5">
                    <p class="text-slate-500 dark:text-slate-400">{{ __('directory.no_churches') }}</p>
                </div>
            @else
                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.church') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('directory.church_accounts') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('directory.general_accounts') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($churches as $church)
                            @php $socialsByCategory = $church->socials->groupBy(fn ($s) => $s->category->value); @endphp
                            <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-xs font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
                                            {{ mb_substr($church->name, 0, 1) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-medium">{{ $church->name }}</p>
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
                                                @php $externalUrl = $social->externalUrl(); @endphp
                                                <div class="flex items-center gap-2">
                                                    @if ($externalUrl)
                                                        {{-- The handle always opens the real profile (so anyone can follow it) — editing is a separate action below, not overloaded onto this link. externalUrl() falls back to a platform+handle-derived URL when profile_url was never filled in (the common case). --}}
                                                        <a href="{{ $externalUrl }}" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-blue-600 dark:hover:text-blue-400">
                                                            <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                            <span>{{ $social->display_handle }}</span>
                                                        </a>
                                                    @else
                                                        <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                                                            <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                            <span>{{ $social->display_handle }}</span>
                                                        </span>
                                                    @endif
                                                    @unless ($social->is_auto_fetch || $social->platform === \App\Enums\SocialPlatform::Facebook)
                                                        <x-icon name="pencil-square" class="h-3.5 w-3.5 shrink-0 text-amber-500" title="{{ __('directory.manual_badge_title') }}" />
                                                    @endunless
                                                </div>
                                            @empty
                                                <span class="text-slate-300 dark:text-slate-600">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @unless ($churches->isEmpty())
            <x-pagination :paginator="$churches" />
        @endunless
    </div>

    {{-- ===================== TAB: PERSONAL ===================== --}}
    <div data-tab-panel="personal">
        <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-3 px-4 py-3">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input
                        type="checkbox"
                        form="directory-filter-form"
                        name="hide_empty_people"
                        value="1"
                        onchange="this.form.submit()"
                        @checked($hideEmptyPeople)
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                    >
                    {{ __('directory.hide_empty_people') }}
                </label>
            </div>

            @if ($people->isEmpty())
                <div class="border-t border-black/5 p-12 text-center dark:border-white/5">
                    <p class="text-slate-500 dark:text-slate-400">{{ __('directory.no_people') }}</p>
                </div>
            @else
                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.name') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($people as $person)
                            <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-xs font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
                                            {{ mb_substr($person->name, 0, 1) }}
                                        </span>
                                        <p class="min-w-0 font-medium">{{ $person->name }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="space-y-1.5">
                                        @forelse ($person->socials as $social)
                                            @php $externalUrl = $social->externalUrl(); @endphp
                                            <div class="flex items-center gap-2">
                                                @if ($externalUrl)
                                                    <a href="{{ $externalUrl }}" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-blue-600 dark:hover:text-blue-400">
                                                        <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                        <span>{{ $social->display_handle }}</span>
                                                    </a>
                                                @else
                                                    <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                                                        <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                        <span>{{ $social->display_handle }}</span>
                                                    </span>
                                                @endif
                                                @unless ($social->is_auto_fetch || $social->platform === \App\Enums\SocialPlatform::Facebook)
                                                    <x-icon name="pencil-square" class="h-3.5 w-3.5 shrink-0 text-amber-500" title="{{ __('directory.manual_badge_title') }}" />
                                                @endunless
                                            </div>
                                        @empty
                                            <span class="text-slate-300 dark:text-slate-600">—</span>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @unless ($people->isEmpty())
            <x-pagination :paginator="$people" />
        @endunless
    </div>

    {{-- ===================== TAB: INSTITUSI ===================== --}}
    <div data-tab-panel="institusi">
        <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-3 px-4 py-3">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input
                        type="checkbox"
                        form="directory-filter-form"
                        name="hide_empty_institutions"
                        value="1"
                        onchange="this.form.submit()"
                        @checked($hideEmptyInstitutions)
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                    >
                    {{ __('directory.hide_empty_institutions') }}
                </label>
            </div>

            @if ($institutions->isEmpty())
                <div class="border-t border-black/5 p-12 text-center dark:border-white/5">
                    <p class="text-slate-500 dark:text-slate-400">{{ __('directory.no_institutions') }}</p>
                </div>
            @else
                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.institution') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($institutions as $institution)
                            <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-xs font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
                                            {{ mb_substr($institution->name, 0, 1) }}
                                        </span>
                                        <p class="min-w-0 font-medium">{{ $institution->name }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="space-y-1.5">
                                        @forelse ($institution->socials as $social)
                                            @php $externalUrl = $social->externalUrl(); @endphp
                                            <div class="flex items-center gap-2">
                                                @if ($externalUrl)
                                                    <a href="{{ $externalUrl }}" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-blue-600 dark:hover:text-blue-400">
                                                        <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                        <span>{{ $social->display_handle }}</span>
                                                    </a>
                                                @else
                                                    <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                                                        <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                        <span>{{ $social->display_handle }}</span>
                                                    </span>
                                                @endif
                                                @unless ($social->is_auto_fetch || $social->platform === \App\Enums\SocialPlatform::Facebook)
                                                    <x-icon name="pencil-square" class="h-3.5 w-3.5 shrink-0 text-amber-500" title="{{ __('directory.manual_badge_title') }}" />
                                                @endunless
                                            </div>
                                        @empty
                                            <span class="text-slate-300 dark:text-slate-600">—</span>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @unless ($institutions->isEmpty())
            <x-pagination :paginator="$institutions" />
        @endunless
    </div>

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
