{{-- Tujuan's two tabs, each with a one-line note saying what it's for and pointing to the other
     one — the two share membership and target figures, so it's spelled out which question each
     answers. Only shown to viewers of Keterlibatan Anggota (admins and Pimpinan from Daerah
     level up); everyone else only has the Capaian page. --}}
@can('view-member-engagement')
    @php
        $onProgress = request()->routeIs('goals.index');
        $periodParam = array_filter(request()->only(['periode']));
    @endphp

    <x-tab-bar>
        <x-tab-link :href="route('goals.index', $periodParam)" :active="$onProgress">{{ __('goals.tab_progress') }}</x-tab-link>
        <x-tab-link :href="route('goals.members', $periodParam)" :active="! $onProgress">{{ __('goals.tab_members') }}</x-tab-link>
    </x-tab-bar>

    <x-tab-note :note="$onProgress ? __('goals.tab_progress_note') : __('goals.tab_members_note')">
        <a href="{{ $onProgress ? route('goals.members', $periodParam) : route('goals.index', $periodParam) }}" class="font-medium underline hover:no-underline">
            {{ $onProgress ? __('goals.tab_progress_link') : __('goals.tab_members_link') }}
        </a>
    </x-tab-note>
@endcan
