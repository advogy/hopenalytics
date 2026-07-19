@php $isEmpty = $row['total'] == 0; @endphp
<div class="flex items-center gap-4 rounded-xl px-4 py-3 {{ $i === 0 ? 'bg-blue-600/20 ring-1 ring-blue-500/40' : 'bg-[#111827]' }} {{ $isEmpty ? 'opacity-40' : '' }}">
    <span class="w-6 shrink-0 text-right text-lg font-semibold text-slate-400">{{ $i + 1 }}</span>

    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-700 text-sm font-bold">
        {{ mb_substr($row['entity']->name, 0, 1) }}
    </span>

    <div class="min-w-0 flex-1">
        <p class="truncate font-medium">{{ $row['entity']->name }}</p>
        @if ($row['entity']->city)
            <p class="truncate text-xs text-slate-400">{{ $row['entity']->city }}</p>
        @endif
    </div>

    <div class="hidden shrink-0 items-center gap-3 sm:flex">
        @foreach (['youtube', 'instagram', 'tiktok', 'facebook'] as $platform)
            <span class="inline-flex items-center gap-1 text-sm {{ $row['byPlatform'][$platform] > 0 ? 'text-slate-200' : 'text-slate-600' }}">
                <x-platform-icon :platform="$platform" class="h-4 w-4" />
                {{ number_format($row['byPlatform'][$platform]) }}
            </span>
        @endforeach
    </div>

    <span class="w-20 shrink-0 text-right text-xl font-bold tabular-nums">{{ number_format($row['total']) }}</span>
</div>
