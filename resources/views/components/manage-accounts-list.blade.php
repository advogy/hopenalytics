{{-- Empty-state-or-list body shared by every "Kelola Akun" accounts page (churches, people,
     and Union/Conference/Institution's organization social-list) — each of those pages only
     really differs in its header (title, back-link, create-button target), not in how the
     account list itself renders. $groupByCategory is true only for churches.social-list, the
     one owner type with a gereja/umum split. --}}
@props(['socials', 'groupByCategory' => false])

@php
    // "Delete" (see x-social-account-row) only ever deactivates a social account (is_active =
    // false) — data stays for history, per its own confirm text — so this list must filter it
    // out itself, otherwise a "deleted" account just sits there unchanged and deleting looks
    // like it did nothing.
    $socials = $socials->where('is_active', true)->values();
    $categoryLabels = ['gereja' => __('directory.church_accounts'), 'umum' => __('directory.general_accounts')];
@endphp

@if ($socials->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
        <p class="text-slate-500 dark:text-slate-400">{{ __('entity.no_socials') }}</p>
    </div>
@elseif ($groupByCategory)
    @php $socialsByCategory = $socials->groupBy(fn ($social) => $social->category->value); @endphp
    @foreach (['gereja', 'umum'] as $category)
        @continue($socialsByCategory->get($category, collect())->isEmpty())

        <h2 class="mb-3 mt-8 text-lg font-medium first:mt-0">{{ $categoryLabels[$category] }}</h2>

        <div class="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($socialsByCategory[$category] as $social)
                    <x-social-account-row :social="$social" />
                @endforeach
            </ul>
        </div>
    @endforeach
@else
    <div class="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($socials as $social)
                <x-social-account-row :social="$social" />
            @endforeach
        </ul>
    </div>
@endif
