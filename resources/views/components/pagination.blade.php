@props(['paginator'])

@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $windowStart = max($current - 1, 1);
        $windowEnd = min($current + 1, $last);
    @endphp

    <div class="mt-4 flex items-center justify-between text-sm">
        <p class="text-slate-500 dark:text-slate-400">
            {{ __('common.pagination_summary', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
        <div class="flex items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-black/10 text-slate-300 dark:border-white/10 dark:text-slate-600">&larr;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-black/10 font-medium text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-700">&larr;</a>
            @endif

            @if ($windowStart > 1)
                <a href="{{ $paginator->url(1) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-black/10 font-medium text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-700">1</a>
                @if ($windowStart > 2)
                    <span class="px-1 text-slate-400 dark:text-slate-500">&hellip;</span>
                @endif
            @endif

            @for ($page = $windowStart; $page <= $windowEnd; $page++)
                @if ($page === $current)
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-blue-600 bg-blue-600 font-medium text-white">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-black/10 font-medium text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-700">{{ $page }}</a>
                @endif
            @endfor

            @if ($windowEnd < $last)
                @if ($windowEnd < $last - 1)
                    <span class="px-1 text-slate-400 dark:text-slate-500">&hellip;</span>
                @endif
                <a href="{{ $paginator->url($last) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-black/10 font-medium text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-700">{{ $last }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-black/10 font-medium text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-700">&rarr;</a>
            @else
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-black/10 text-slate-300 dark:border-white/10 dark:text-slate-600">&rarr;</span>
            @endif
        </div>
    </div>
@endif
