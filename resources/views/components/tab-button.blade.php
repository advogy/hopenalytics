{{-- One button inside x-tab-bar. $tabKey matches the data-tab-panel it activates (see partials/tab-script.blade.php). --}}
@props(['tabKey'])

<button type="button" data-tab-button="{{ $tabKey }}" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
    {{ $slot }}
</button>
