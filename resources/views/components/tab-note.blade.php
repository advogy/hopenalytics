{{--
    The note under a tab bar saying what the active tab is for. In-page tabs (x-tab-bar with
    x-tab-button note="...") get an empty one that partials/tab-script fills in and swaps as
    tabs change; link tabs (x-hub-header, Tujuan) render the active tab's note directly. An
    optional slot adds a link after the text.
--}}
@props(['note' => null, 'live' => false])

<div
    @if ($live) data-tab-note-box @endif
    {{ $attributes->class(['-mt-2 mb-6 flex items-start gap-2 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:bg-blue-950/40 dark:text-blue-200', 'hidden' => $live || blank($note)]) }}
>
    <x-icon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
    <p>
        <span @if ($live) data-tab-note-text @endif>{{ $note }}</span>
        {{ $slot }}
    </p>
</div>
