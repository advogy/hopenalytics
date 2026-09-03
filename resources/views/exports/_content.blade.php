<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0 flex-1">
        <h2 class="text-xl font-semibold tracking-tight">{{ $dataset['title'] }}</h2>
        @if ($dataset['subtitle'])
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $dataset['subtitle'] }}</p>
        @endif
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('export.preview_row_count', ['count' => count($dataset['rows'])]) }}</p>

        @if (! empty($dataset['summary']))
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($dataset['summary'] as $item)
                    <div class="rounded-lg border border-black/5 bg-white px-3 py-1.5 dark:border-white/5 dark:bg-slate-900">
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $item['label'] }}:</span>
                        <span class="ml-1 text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ $item['value'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="flex shrink-0 items-center gap-2">
        <a href="{{ $pdfDownloadUrl }}" title="{{ __('export.download_pdf') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-red-700">
            <x-icon name="arrow-down-tray" class="h-3.5 w-3.5" />
            {{ __('export.format_pdf') }}
        </a>
        <a href="{{ $wordDownloadUrl }}" title="{{ __('export.download_word') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700">
            <x-icon name="arrow-down-tray" class="h-3.5 w-3.5" />
            {{ __('export.format_word') }}
        </a>
        <a href="{{ $excelDownloadUrl }}" title="{{ __('export.download_excel') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-700">
            <x-icon name="arrow-down-tray" class="h-3.5 w-3.5" />
            {{ __('export.format_excel') }}
        </a>
    </div>
</div>

@if (empty($dataset['rows']))
    <x-empty-state>{{ __('export.no_data') }}</x-empty-state>
@else
    <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    @foreach ($dataset['headers'] as $header)
                        <th class="whitespace-nowrap px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                @foreach ($dataset['rows'] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td class="whitespace-nowrap px-4 py-2.5 tabular-nums">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if (! empty($dataset['totals']))
                <tfoot class="border-t-2 border-black/5 bg-slate-50 dark:border-white/5 dark:bg-slate-800/60">
                    <tr>
                        @foreach ($dataset['totals'] as $cell)
                            <td class="whitespace-nowrap px-4 py-2.5 font-bold tabular-nums">{{ $cell }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endif

<p class="mt-6 text-xs text-slate-400 dark:text-slate-500">{{ $footer }}</p>
