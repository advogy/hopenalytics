@props(['title', 'subtitle', 'rows', 'valueLabel', 'viewAllUrl' => null, 'exportUrl' => null, 'nameLabel' => null])

@php $nameLabel ??= __('common.name'); @endphp

<div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <div class="mb-1 flex items-start justify-between gap-2">
        <p class="font-bold text-slate-900 dark:text-white">{{ $title }}</p>
        <div class="flex shrink-0 items-center gap-3 text-sm">
            @if ($exportUrl)
                <a href="{{ $exportUrl }}" data-export-trigger class="text-blue-600 hover:underline dark:text-blue-400">
                    {{ __('common.export') }}
                </a>
            @endif
            @if ($viewAllUrl)
                <a href="{{ $viewAllUrl }}" class="text-blue-600 hover:underline dark:text-blue-400">
                    {{ __('common.view_all') }}
                </a>
            @endif
        </div>
    </div>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>

    @if ($rows->isEmpty())
        <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">
            {{ __('common.no_growth_data') }}
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-slate-500 dark:text-slate-400">
                        <th class="py-2 pr-2 font-medium">#</th>
                        <th class="py-2 pr-2 font-medium">{{ $nameLabel }}</th>
                        <th class="py-2 pr-2 font-medium">Akun</th>
                        <th class="py-2 pr-2 text-right font-medium">{{ __('comparison.growth') }}</th>
                        <th class="py-2 text-right font-medium">{{ $valueLabel }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($rows as $i => $row)
                        @php $entity = $row['social']->church ?? $row['social']->person; @endphp
                        <tr>
                            <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $i + 1 }}</td>
                            <td class="py-2 pr-2">
                                <a
                                    href="{{ $row['social']->church ? route('churches.show', $entity) : route('people.show', $entity) }}"
                                    class="font-medium hover:text-blue-600 dark:hover:text-blue-400"
                                >
                                    {{ $row['social']->display_name }}
                                </a>
                            </td>
                            <td class="py-2 pr-2">
                                <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                    <x-platform-icon :platform="$row['social']->platform" class="h-4.5 w-4.5" />
                                    {{ $row['social']->display_handle }}
                                </span>
                            </td>
                            <td class="py-2 pr-2 text-right font-medium tabular-nums {{ $row['delta'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($row['delta'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                                <span class="inline-flex items-center gap-1">
                                    <x-icon :name="$row['delta'] > 0 ? 'arrow-trending-up' : ($row['delta'] < 0 ? 'arrow-trending-down' : 'minus-small')" class="h-3.5 w-3.5" />
                                    {{ $row['delta'] > 0 ? '+' : '' }}{{ number_format($row['delta']) }}
                                </span>
                            </td>
                            <td class="py-2 text-right tabular-nums">{{ number_format($row['latest']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
