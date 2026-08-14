{{-- Standard "&larr; back to X" link used at the top of nearly every content page. --}}
@props(['href'])

<a href="{{ $href }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
    &larr; {{ $slot }}
</a>
