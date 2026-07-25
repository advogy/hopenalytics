{{--
    Date-range filter for one Data Per * tab's growth-over-time chart — only narrows the chart
    (see the hint tooltip), not the KPI hero or table. Native <input type="date"> + preset
    buttons that just compute two dates and submit, rather than a full calendar-picker library
    (data is weekly, so a fine-grained calendar UI isn't worth the extra dependency/bundle size).

    Expected: $prefix (unique per tab, for the field ids), $selectedStartDate, $selectedEndDate.
--}}
<div class="flex flex-wrap items-center gap-2" title="{{ __('analytics.date_range_filter_hint') }}">
    <div class="flex items-center gap-1.5 rounded-full border border-black/10 bg-slate-50 py-2 pr-3 pl-9 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700 relative">
        <x-icon name="calendar" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
            type="date"
            name="start_date"
            id="{{ $prefix }}-start-date"
            value="{{ $selectedStartDate }}"
            onchange="this.form.submit()"
            class="border-0 bg-transparent p-0 text-sm font-medium text-slate-700 focus:ring-0 dark:text-slate-200"
        >
        <span class="text-sm text-slate-400">–</span>
        <input
            type="date"
            name="end_date"
            id="{{ $prefix }}-end-date"
            value="{{ $selectedEndDate }}"
            onchange="this.form.submit()"
            class="border-0 bg-transparent p-0 text-sm font-medium text-slate-700 focus:ring-0 dark:text-slate-200"
        >
    </div>

    <div class="flex flex-wrap gap-1">
        @foreach (['this-month' => 'preset_this_month', 'last-month' => 'preset_last_month', '30-days' => 'preset_30_days', '60-days' => 'preset_60_days', '90-days' => 'preset_90_days', 'this-year' => 'preset_this_year', 'last-year' => 'preset_last_year'] as $preset => $labelKey)
            <button
                type="button"
                onclick="window.applyAnalyticsDatePreset('{{ $prefix }}-start-date', '{{ $prefix }}-end-date', '{{ $preset }}')"
                class="rounded-full border border-black/10 px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-slate-800"
            >
                {{ __("analytics.{$labelKey}") }}
            </button>
        @endforeach
    </div>
</div>

@once
    <script>
        window.applyAnalyticsDatePreset = function (startFieldId, endFieldId, preset) {
            var today = new Date();
            var start, end;

            function fmt(d) {
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            }

            if (preset === 'this-month') {
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                end = today;
            } else if (preset === 'last-month') {
                start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                end = new Date(today.getFullYear(), today.getMonth(), 0);
            } else if (preset === '30-days') {
                start = new Date(today); start.setDate(start.getDate() - 30);
                end = today;
            } else if (preset === '60-days') {
                start = new Date(today); start.setDate(start.getDate() - 60);
                end = today;
            } else if (preset === '90-days') {
                start = new Date(today); start.setDate(start.getDate() - 90);
                end = today;
            } else if (preset === 'this-year') {
                start = new Date(today.getFullYear(), 0, 1);
                end = today;
            } else if (preset === 'last-year') {
                start = new Date(today.getFullYear() - 1, 0, 1);
                end = new Date(today.getFullYear() - 1, 11, 31);
            }

            var startField = document.getElementById(startFieldId);
            var endField = document.getElementById(endFieldId);
            startField.value = fmt(start);
            endField.value = fmt(end);
            startField.form.submit();
        };
    </script>
@endonce
