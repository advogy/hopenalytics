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

@foreach ($socials as $social)
    <x-social-history-table
        :social="$social"
        :history-rows="$history[$social->id] ?? collect()"
        :category-label="$categoryLabelFor($social)"
    />
@endforeach
