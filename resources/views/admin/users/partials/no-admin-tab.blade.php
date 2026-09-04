{{--
    "Belum Ada Admin" — a read-only checklist of orgs the actor can already see (each list reuses
    that model's own scopeVisibleTo(), so nothing shows up here that wouldn't already show up
    elsewhere in Kelola Akun/Analitik) that have nobody holding the matching admin role yet.
    Assigning one is still done from the "Admin" tab's own add-admin form — this tab is purely
    "here's what still needs attention," not a new assignment flow.

    Grouped hierarchically (Uni → Daerah → Gereja) rather than flat lists, per the user's explicit
    follow-up call — a bare list of 17 church names means nothing without knowing which wilayah
    each one is in. Same reasoning extends the optional Uni filter above it: once nationwide lists
    get long, narrowing to one Uni first is the natural way to actually work through them.

    Expects: $noAdminUnions, $noAdminConferences, $noAdminChurches, $noAdminInstitutions,
    $noAdminUnionOptions, $noAdminConferenceOptions, $noAdminSelectedUnionId,
    $noAdminSelectedConferenceId, $isNasionalView, $isUniView, $canManageInstitutions, $activeTab.
--}}
{{-- Same Uni → Daerah cascading searchable-select used on Analitik & Grafik (see
     partials.analytics-region-filter), inside the identical <x-filter-card> wrapper — reused
     verbatim rather than a plain <select>, per the user's explicit call. --}}
<x-filter-card :clear-url="($noAdminSelectedUnionId || $noAdminSelectedConferenceId) ? route('admin.users.index', ['tab' => 'belum-admin']) : null">
    <form method="GET" id="belum-admin-filter-form" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
        @include('partials.analytics-region-filter', [
            'prefix' => 'belum-admin',
            'formId' => 'belum-admin-filter-form',
            'isNasionalView' => $isNasionalView,
            'isUniView' => $isUniView,
            'unionOptions' => $noAdminUnionOptions,
            'conferenceOptions' => $noAdminConferenceOptions,
            'selectedUnionId' => $noAdminSelectedUnionId,
            'selectedConferenceId' => $noAdminSelectedConferenceId,
        ])
    </form>
</x-filter-card>

<div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('users.no_admin_title') }}</p>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('users.no_admin_subtitle') }}</p>

    {{-- $noAdminTotal itself is already computed once by index.blade.php (it needs the number
         for the tab button's own badge, rendered before this partial is even included) — shared
         Blade @include scope means it's already available here with no need to recompute it. --}}
    @if ($noAdminTotal === 0)
        <x-empty-state variant="inline">{{ __('users.no_admin_group_empty') }}</x-empty-state>
    @else
        <div class="space-y-6">
            @if ($noAdminUnions->isNotEmpty())
                <div>
                    <p class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase dark:text-slate-500">
                        {{ __('users.no_admin_group_union') }} ({{ $noAdminUnions->count() }})
                    </p>
                    <ul class="flex flex-wrap gap-2 text-sm">
                        @foreach ($noAdminUnions as $union)
                            <li class="rounded-lg border border-black/5 px-3 py-1.5 font-medium text-slate-900 dark:border-white/5 dark:text-white">{{ $union->name }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($noAdminConferences->isNotEmpty())
                <div>
                    <p class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase dark:text-slate-500">
                        {{ __('users.no_admin_group_conference') }} ({{ $noAdminConferences->count() }})
                    </p>
                    <div class="space-y-3">
                        @foreach ($noAdminConferences->groupBy(fn ($c) => $c->union?->name ?? '—') as $unionName => $conferences)
                            <div>
                                <p class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $unionName }}</p>
                                <ul class="flex flex-wrap gap-2 text-sm">
                                    @foreach ($conferences as $conference)
                                        <li class="rounded-lg border border-black/5 px-3 py-1.5 font-medium text-slate-900 dark:border-white/5 dark:text-white">{{ $conference->name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($noAdminChurches->isNotEmpty())
                <div>
                    <p class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase dark:text-slate-500">
                        {{ __('users.no_admin_group_church') }} ({{ $noAdminChurches->count() }})
                    </p>
                    <div class="space-y-3">
                        @foreach ($noAdminChurches->groupBy(fn ($c) => ($c->conference?->union?->name ?? '—') . ' — ' . ($c->conference?->name ?? '—')) as $groupLabel => $churches)
                            <div>
                                <p class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $groupLabel }}</p>
                                <ul class="flex flex-wrap gap-2 text-sm">
                                    @foreach ($churches as $church)
                                        <li class="rounded-lg border border-black/5 px-3 py-1.5 font-medium text-slate-900 dark:border-white/5 dark:text-white">{{ $church->name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($noAdminInstitutions->isNotEmpty())
                <div>
                    <p class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase dark:text-slate-500">
                        {{ __('users.no_admin_group_institution') }} ({{ $noAdminInstitutions->count() }})
                    </p>
                    <ul class="flex flex-wrap gap-2 text-sm">
                        @foreach ($noAdminInstitutions as $institution)
                            <li class="rounded-lg border border-black/5 px-3 py-1.5 font-medium text-slate-900 dark:border-white/5 dark:text-white">{{ $institution->name }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <p class="mt-6 text-xs text-slate-400 dark:text-slate-500">{{ __('users.no_admin_hint') }}</p>
    @endif
</div>
