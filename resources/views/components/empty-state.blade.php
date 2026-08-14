{{--
    "Nothing here yet" message, in one of two weights used across the app:
    - card (default): a full dashed-border card, for a whole page/section with no data at all.
    - inline: a bare centered line, for a smaller "no rows match" spot inside an existing card
      (e.g. a table's tbody-replacement when a filtered list comes back empty).
--}}
@props(['variant' => 'card'])

@if ($variant === 'inline')
    <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $slot }}</p>
@else
    <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
        <p class="text-slate-500 dark:text-slate-400">{{ $slot }}</p>
    </div>
@endif
