{{--
    One "Grand Total" table cell's content in churches/analytics.blade.php: a platform-grouped
    account-count chip row plus a platform-grouped follower/subscriber-sum chip row (or a dash
    when the group is empty). Used once per tab for Organisasi/Institusi/Personal, and twice
    (Gereja/Umum) for the Gereja tab's per-category cells.

    - group: a Collection of SocialAccount already grouped by platform value (->groupBy(fn ($s) => $s->platform->value))
    - countField: ['youtube' => 'subscribers_count', ...] map, see churches/analytics.blade.php's own $countField
--}}
@props(['group', 'countField'])

@if ($group->isEmpty())
    <span class="text-slate-300 dark:text-slate-600">—</span>
@else
    <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('analytics.account_count') }}</p>
    <div class="mb-2 flex flex-wrap gap-1.5">
        @foreach ($group as $platformValue => $platformGroup)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                <x-platform-icon :platform="$platformValue" class="h-4.5 w-4.5" />
                <span class="font-semibold tabular-nums">{{ $platformGroup->count() }}</span>
            </span>
        @endforeach
    </div>

    <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('analytics.total_followers_subscribers') }}</p>
    <div class="flex flex-wrap gap-1.5">
        @foreach ($group as $platformValue => $platformGroup)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                <x-platform-icon :platform="$platformValue" class="h-4.5 w-4.5" />
                <span class="font-semibold tabular-nums">
                    {{ number_format($platformGroup->sum(fn ($s) => $s->latestStat?->{$countField[$platformValue]} ?? 0)) }}
                </span>
            </span>
        @endforeach
    </div>
@endif
