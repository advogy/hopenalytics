{{--
    Title and tab bar for a page that belongs to one of the admin hubs (App\Support\AdminHubs).
    The page's own heading goes below it as an h2.
--}}
@props(['hub'])

@php
    $tabs = \App\Support\AdminHubs::tabs($hub, auth()->user());
@endphp

<h1 class="mb-4 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.hub_'.$hub) }}</h1>

@if (count($tabs) > 1)
    <x-tab-bar>
        @foreach ($tabs as $tab)
            <x-tab-link :href="$tab['url']" :active="$tab['active']" :badge="$tab['badge']">{{ $tab['label'] }}</x-tab-link>
        @endforeach
    </x-tab-bar>

    {{-- These tabs are separate pages, so the active one's note is rendered here directly. --}}
    @php($activeTab = collect($tabs)->firstWhere('active', true))
    @if ($activeTab)
        <x-tab-note :note="$activeTab['note']" />
    @endif
@endif
