@php
    $church = $row['church'];
    $percent = $maxReach > 0 ? round($row['reach'] / $maxReach * 100, 1) : 0;
    $isEmpty = $row['reach'] == 0 && $row['views'] == 0 && $row['likes'] == 0 && $row['posts'] == 0;
    $namePaddingClass = match ($depth ?? 0) {
        1 => 'pl-8',
        2 => 'pl-12',
        default => 'pl-4',
    };
@endphp
<tr
    class="hover:bg-slate-50 dark:hover:bg-slate-800/40"
    data-church-row
    @if ($isEmpty) data-empty-row @endif
    @if ($ancestors ?? null) data-group-ancestors="{{ $ancestors }}" @endif
>
    <td class="{{ $namePaddingClass }} pr-4 py-3">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-xs font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
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
            <div class="flex flex-wrap gap-1.5">
                @forelse ($row['socialsByCategory']->get($category, collect()) as $social)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                        <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                        <span class="font-medium tabular-nums">
                            {{ number_format($social->latestStat?->{$countField[$social->platform->value]} ?? 0) }}
                        </span>
                    </span>
                @empty
                    <span class="text-slate-300 dark:text-slate-600">—</span>
                @endforelse
            </div>
        </td>
    @endforeach
    <td class="px-4 py-3">
        <div class="flex items-center gap-3">
            <span class="w-16 shrink-0 text-right font-semibold tabular-nums">{{ number_format($row['reach']) }}</span>
            <div class="relative h-1.5 w-32 shrink-0 rounded-full bg-slate-100 dark:bg-slate-700">
                <div class="h-1.5 rounded-full bg-blue-500 dark:bg-blue-400" style="width: {{ $percent }}%"></div>
                <span class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 h-2.5 w-2.5 rounded-full border-2 border-white bg-blue-600 shadow dark:border-slate-900" style="left: {{ $percent }}%"></span>
            </div>
            <span class="w-10 shrink-0 text-xs text-slate-400 dark:text-slate-500">{{ $percent }}%</span>
        </div>
    </td>
    <td class="px-4 py-3 text-right tabular-nums">{{ $row['views'] ? number_format($row['views']) : '—' }}</td>
    <td class="px-4 py-3 text-right tabular-nums">{{ $row['likes'] ? number_format($row['likes']) : '—' }}</td>
    <td class="px-4 py-3 text-right tabular-nums">{{ $row['posts'] ? number_format($row['posts']) : '—' }}</td>
</tr>
