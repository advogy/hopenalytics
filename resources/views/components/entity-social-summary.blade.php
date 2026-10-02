{{--
    The shared tail of every entity "show" page (churches/institutions/organizations/people): empty
    state, growth-score summary, the social-account-card grid, then a history table per account.

    - categories: optional ['gereja' => label, 'umum' => label]-style map — only churches.show uses
      this, to split the account-card grid into headed sections and pass a category label into each
      history table. Omit for entities with no category concept (institution/organization/person).
--}}
@props([
    'socials',
    'history',
    'scoreHistory',
    'scoreMetrics',
    'scoreBreakdown',
    'scoreSampleCount',
    'scoreSampleSum',
    'anchored' => false,
    'showRecentContent' => true,
    'categories' => null,
])

@php
    $categoryLabelFor = fn ($social) => $categories !== null ? ($categories[$social->category->value] ?? null) : null;
@endphp

@if ($socials->isEmpty())
    <x-empty-state>{{ __('entity.no_socials') }}</x-empty-state>
@endif

@if ($socials->isNotEmpty())
    <x-growth-score-summary
        :score-history="$scoreHistory"
        :score-metrics="$scoreMetrics"
        :score-breakdown="$scoreBreakdown"
        :score-sample-count="$scoreSampleCount"
        :score-sample-sum="$scoreSampleSum"
        :anchored="$anchored"
    />

    @if ($categories !== null)
        @php $socialsByCategory = $socials->groupBy(fn ($social) => $social->category->value); @endphp
        @foreach ($categories as $categoryKey => $categoryLabel)
            @continue($socialsByCategory->get($categoryKey, collect())->isEmpty())

            <h2 class="mb-3 mt-8 text-lg font-medium first:mt-0">{{ $categoryLabel }}</h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($socialsByCategory[$categoryKey] as $social)
                    <x-social-account-card :social="$social" :history-rows="$history[$social->id] ?? collect()" :show-recent-content="$showRecentContent" />
                @endforeach
            </div>
        @endforeach
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($socials as $social)
                <x-social-account-card :social="$social" :history-rows="$history[$social->id] ?? collect()" :show-recent-content="$showRecentContent" />
            @endforeach
        </div>
    @endif
@endif

{{-- Every account's history in ONE card with a tab per account, instead of one full table per
     account stacked down the page — per the user's explicit call: as accounts/history grew, the
     page kept scrolling further and further down. Each table also scrolls inside its own fixed
     height (see social-history-table). Scoped JS rather than partials/tab-script.blade.php, which
     wires up every [data-tab-button] on the whole page and would clash with a page's own tabs. --}}
@php
    $socialsWithHistory = $socials->filter(fn ($social) => ($history[$social->id] ?? collect())->isNotEmpty())->values();
    $historyTabsId = 'social-history-'.\Illuminate\Support\Str::random(8);
@endphp

@if ($socialsWithHistory->isNotEmpty())
    <div id="{{ $historyTabsId }}" class="mt-8 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <div class="mb-4">
            <h2 class="font-medium">{{ __('entity.history_section_title') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('entity.history_section_subtitle') }}</p>
        </div>

        @if ($socialsWithHistory->count() > 1)
            <div class="mb-4 flex gap-1 overflow-x-auto border-b border-black/5 dark:border-white/5" role="tablist">
                @foreach ($socialsWithHistory as $social)
                    <button
                        type="button"
                        role="tab"
                        data-history-tab="{{ $social->id }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        @class([
                            '-mb-px flex shrink-0 cursor-pointer items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition',
                            'border-blue-600 text-blue-600 dark:text-blue-400' => $loop->first,
                            'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => ! $loop->first,
                        ])
                    >
                        <x-platform-icon :platform="$social->platform" class="h-5 w-5 text-[10px]" />
                        <span>{{ $social->display_handle }}</span>
                        @if ($label = $categoryLabelFor($social))
                            <span class="text-xs font-normal text-slate-400 dark:text-slate-500">({{ $label }})</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @else
            @php $only = $socialsWithHistory->first(); @endphp
            <div class="mb-3 flex items-center gap-2 text-sm font-medium">
                <x-platform-icon :platform="$only->platform" class="h-5 w-5 text-[10px]" />
                <span>{{ $only->display_handle }}</span>
                @if ($label = $categoryLabelFor($only))
                    <span class="text-xs font-normal text-slate-400 dark:text-slate-500">({{ $label }})</span>
                @endif
            </div>
        @endif

        @foreach ($socialsWithHistory as $social)
            <div data-history-panel="{{ $social->id }}" role="tabpanel" @class(['hidden' => ! $loop->first])>
                <x-social-history-table :social="$social" :history-rows="$history[$social->id]" />
            </div>
        @endforeach
    </div>

    @if ($socialsWithHistory->count() > 1)
        <script>
            (function () {
                var root = document.getElementById(@json($historyTabsId));
                if (! root) return;

                var activeClasses = ['border-blue-600', 'text-blue-600', 'dark:text-blue-400'];
                var inactiveClasses = ['border-transparent', 'text-slate-500', 'hover:text-slate-700', 'dark:text-slate-400', 'dark:hover:text-slate-200'];

                root.addEventListener('click', function (e) {
                    var tab = e.target.closest('[data-history-tab]');
                    if (! tab) return;

                    var key = tab.getAttribute('data-history-tab');
                    root.querySelectorAll('[data-history-tab]').forEach(function (button) {
                        var isActive = button === tab;
                        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        activeClasses.forEach(function (c) { button.classList.toggle(c, isActive); });
                        inactiveClasses.forEach(function (c) { button.classList.toggle(c, ! isActive); });
                    });
                    root.querySelectorAll('[data-history-panel]').forEach(function (panel) {
                        panel.classList.toggle('hidden', panel.getAttribute('data-history-panel') !== key);
                    });
                });
            })();
        </script>
    @endif
@endif
