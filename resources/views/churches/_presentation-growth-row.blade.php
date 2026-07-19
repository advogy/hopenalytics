@php
    $metricLabels = ['reach' => 'Reach', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];
    $hasScore = $row['score'] !== null;
@endphp
<div class="flex items-center gap-4 rounded-xl px-4 py-3 {{ $i === 0 ? 'bg-blue-600/20 ring-1 ring-blue-500/40' : 'bg-[#111827]' }} {{ $hasScore ? '' : 'opacity-40' }}">
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
        @foreach ($metricLabels as $key => $label)
            @php $value = $row['metrics'][$key] ?? null; @endphp
            <span class="inline-flex items-center gap-1 text-sm {{ $value === null ? 'text-slate-600' : 'text-slate-200' }}">
                <span class="text-slate-500">{{ $label }}</span>
                @if ($value === null)
                    &ndash;
                @else
                    <span class="{{ $value > 0 ? 'text-emerald-400' : ($value < 0 ? 'text-red-400' : 'text-slate-400') }}">
                        {{ $value > 0 ? '+' : '' }}{{ number_format($value, 1) }}%
                    </span>
                @endif
            </span>
        @endforeach
    </div>

    <span class="w-20 shrink-0 text-right text-xl font-bold tabular-nums {{ $hasScore && $row['score'] < 0 ? 'text-red-400' : '' }}">
        @if ($hasScore)
            {{ $row['score'] > 0 ? '+' : '' }}{{ number_format($row['score'], 1) }}%
        @else
            &ndash;
        @endif
    </span>
</div>
