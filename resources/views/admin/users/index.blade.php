@php
    $levelLabels = ['nasional' => __('common.national'), 'divisi' => __('common.division'), 'uni' => __('common.union'), 'daerah' => __('common.conference'), 'gereja' => __('common.church')];
    $scopeLabel = match ($targetLevel) {
        'divisi' => __('common.division'),
        'uni' => __('common.union'),
        'daerah' => __('common.conference'),
        'gereja' => __('common.church'),
        default => null,
    };

    // Shared by "Semua User" and "Daftar Admin & Pimpinan"'s own "Peran & Wilayah" column: the
    // parent org (Union for a Daerah admin, Conference for a Gereja admin, ...) on its own line
    // under the entity's own name, rather than parenthesized on the same line — per the user's
    // explicit call. A null parent (e.g. a church with no conference) renders as no second line
    // instead of an empty "()" one.
    $scopeDisplayPartsFor = function ($user) {
        return match ($user->role?->level()) {
            'nasional' => ['label' => $user->assignedUnions->isNotEmpty() ? $user->assignedUnions->pluck('name')->implode(', ') : '—', 'parent' => null],
            'divisi' => ['label' => $user->division?->name ?? '—', 'parent' => null],
            'uni' => $user->union ? ['label' => $user->union->name, 'parent' => $user->union->division?->name] : ['label' => '—', 'parent' => null],
            'daerah' => $user->conference ? ['label' => $user->conference->name, 'parent' => $user->conference->union->name] : ['label' => '—', 'parent' => null],
            'gereja' => $user->church ? ['label' => $user->church->name, 'parent' => $user->church->conference?->name] : ['label' => '—', 'parent' => null],
            'institusi' => ['label' => $user->institution?->name ?? '—', 'parent' => null],
            default => ['label' => '—', 'parent' => null],
        };
    };

@endphp

@extends('layouts.app')

@section('title', __('nav.manage_users') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.manage_users') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            @if ($canBootstrapAnyLevel)
                {{ __('users.subtitle_any_level') }}
            @else
                {{ __('users.subtitle_own_level', ['level' => $levelLabels[$targetLevel]]) }}
            @endif
        </p>
    </div>

    <x-tab-bar>
        <x-tab-button tab-key="all">{{ __('users.tab_all') }}</x-tab-button>
        @if ($canReviewSuggestions)
            <x-tab-button tab-key="saran">
                {{ __('admin_suggestions.tab_label') }}
                @if ($pendingSuggestions->total() > 0)
                    <span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                        {{ $pendingSuggestions->total() }}
                    </span>
                @endif
            </x-tab-button>
        @endif
        @php($noAdminTotal = $noAdminUnions->count() + $noAdminConferences->count() + $noAdminChurches->count() + $noAdminInstitutions->count())
        <x-tab-button tab-key="belum-admin">
            {{ __('users.tab_belum_admin') }}
            @if ($noAdminTotal > 0)
                <span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                    {{ $noAdminTotal }}
                </span>
            @endif
        </x-tab-button>
        <x-tab-button tab-key="admin">{{ __('users.tab_admin') }}</x-tab-button>
        @if ($isSuperAdmin)
            <x-tab-button tab-key="terhapus">{{ __('users.tab_terhapus') }}</x-tab-button>
        @endif
    </x-tab-bar>

    <div id="semua-user" data-tab-panel="all" @class(['hidden' => $activeTab !== 'all'])>
        {{-- Same shared external-form shape as Monitoring Antrean's own bulk actions (see
             partials/bulk-select.blade.php) — checkboxes below reference this purely via
             form="all-bulk-form" since they can't nest inside it (each row's own Edit/Ganti
             Wilayah/Cabut/etc. are already their own form/button), and the two bulk buttons each
             override where this same set of checked ids goes via their own formaction. --}}
        <form method="POST" id="all-bulk-form" data-disable-on-submit>@csrf</form>

        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-stat-card
                icon="users"
                :label="__('users.all_stat_label')"
                :value="number_format($allUsersTotal)"
                :hint="__('users.all_stat_hint')"
            />
            <x-stat-card
                :href="route('admin.users.index', ['tab' => 'all', 'all_verification' => 'pending'])"
                icon="clock"
                :label="__('users.all_stat_unverified_label')"
                :value="number_format($allUsersUnverifiedTotal)"
                :hint="__('users.all_stat_unverified_hint')"
            />
            <x-stat-card
                :href="route('admin.users.index', ['tab' => 'all', 'all_role' => 'unassigned'])"
                icon="user"
                :label="__('users.tab_unassigned')"
                :value="number_format($allUsersUnassignedTotal)"
                :hint="__('users.all_stat_unassigned_hint')"
            />
        </div>

        {{-- Own top-level box, not nested inside the results card below — same layout Analitik &
             Grafik uses (a filter card, then a separate results card) rather than
             components/admin-list-card.blade.php's own single all-in-one-box shape. --}}
        <x-filter-card :clear-url="($allSearch !== '' || $allRole !== 'all' || $allVerification !== 'all' || $allSort !== 'date_desc') ? route('admin.users.index', ['tab' => 'all']) : null">
            <form method="GET" class="flex flex-wrap gap-3">
                <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
                <label class="relative block w-full max-w-sm flex-1">
                    <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="search"
                        name="all_search"
                        value="{{ $allSearch }}"
                        placeholder="{{ __('users.search_placeholder') }}"
                        class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                    >
                </label>
                <label class="relative min-w-[200px]">
                    <select
                        name="all_role"
                        onchange="this.form.submit()"
                        class="w-full appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-4 text-sm font-medium text-slate-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <option value="all" @selected($allRole === 'all')>{{ __('users.filter_role_all') }}</option>
                        <option value="unassigned" @selected($allRole === 'unassigned')>{{ __('users.tab_unassigned') }}</option>
                        @foreach ($allRoleOptions as $roleOption)
                            <option value="{{ $roleOption->value }}" @selected($allRole === $roleOption->value)>{{ $roleOption->label() }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>
                <label class="relative min-w-[200px]">
                    <select
                        name="all_verification"
                        onchange="this.form.submit()"
                        class="w-full appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-4 text-sm font-medium text-slate-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <option value="all" @selected($allVerification === 'all')>{{ __('users.filter_verification_all') }}</option>
                        <option value="pending" @selected($allVerification === 'pending')>{{ __('users.pending_verification') }}</option>
                        <option value="verified" @selected($allVerification === 'verified')>{{ __('users.verified') }}</option>
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>
                <label class="relative min-w-[200px]">
                    <select
                        name="all_sort"
                        onchange="this.form.submit()"
                        class="w-full appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-4 text-sm font-medium text-slate-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <option value="date_desc" @selected($allSort === 'date_desc')>{{ __('users.sort_date_desc') }}</option>
                        <option value="date_asc" @selected($allSort === 'date_asc')>{{ __('users.sort_date_asc') }}</option>
                        <option value="name_asc" @selected($allSort === 'name_asc')>{{ __('users.sort_name_asc') }}</option>
                        <option value="name_desc" @selected($allSort === 'name_desc')>{{ __('users.sort_name_desc') }}</option>
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>
            </form>
        </x-filter-card>

        {{-- Same card shape as Analitik & Grafik's own "Data Per Divisi/Uni/Daerah" table (see
             churches/analytics.blade.php) — a title row, then the table under a border-t,
             instead of components/admin-list-card.blade.php's own single-box shape (still used,
             unchanged, by every other tab on this page). --}}
        <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('users.all_title') }}</h2>
                @if ($allUsers->isNotEmpty())
                    <div class="flex items-center gap-3">
                        <button
                            type="submit"
                            form="all-bulk-form"
                            formaction="{{ route('admin.users.resend-otp-bulk') }}"
                            data-bulk-resend-otp-button
                            data-confirm-template="{{ __('users.resend_otp_selected_confirm', ['count' => ':count']) }}"
                            disabled
                            title="{{ __('users.resend_otp_selected') }}"
                            aria-label="{{ __('users.resend_otp_selected') }}"
                            class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-blue-400 dark:hover:bg-blue-950/40 dark:hover:text-blue-300"
                        >
                            <x-icon name="arrow-path" class="h-5 w-5" />
                        </button>
                        <button
                            type="submit"
                            form="all-bulk-form"
                            formaction="{{ route('admin.users.deactivate-bulk') }}"
                            data-bulk-deactivate-button
                            data-confirm-template="{{ __('users.deactivate_selected_confirm', ['count' => ':count']) }}"
                            disabled
                            title="{{ __('users.deactivate_selected') }}"
                            aria-label="{{ __('users.deactivate_selected') }}"
                            class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                        >
                            <x-icon name="x-circle" class="h-5 w-5" />
                        </button>
                        <button
                            type="submit"
                            form="all-bulk-form"
                            formaction="{{ route('admin.users.destroy-bulk') }}"
                            data-bulk-delete-button
                            data-confirm-template="{{ __('users.delete_selected_confirm', ['count' => ':count']) }}"
                            disabled
                            title="{{ __('users.delete_selected') }}"
                            aria-label="{{ __('users.delete_selected') }}"
                            class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                        >
                            <x-icon name="trash" class="h-5 w-5" />
                        </button>
                    </div>
                @endif
            </div>

            @if ($allUsers->isEmpty())
                <div class="border-t border-black/5 px-6 py-8 dark:border-white/5">
                    <x-empty-state variant="inline">{{ __('users.no_match_all') }}</x-empty-state>
                </div>
            @else
            <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                <table class="w-full text-left text-sm">
            <thead>
                <tr class="bg-slate-50 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                    <th class="w-8 px-4 py-2.5">
                        <input type="checkbox" data-select-all-all-users aria-label="{{ __('users.select_all') }}" title="{{ __('users.select_all') }}" class="h-4 w-4 cursor-pointer rounded border-black/20 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th class="px-4 py-2.5 font-semibold">#</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('users.col_user') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('users.col_registered_at') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('users.col_role_scope') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('common.status') }}</th>
                    <th class="min-w-[9rem] px-4 py-2.5 font-semibold">{{ __('common.action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($allUsers as $user)
                    <tr>
                        <td class="px-4 py-2.5">
                            <input type="checkbox" name="ids[]" value="{{ $user->id }}" form="all-bulk-form" data-all-user-checkbox class="h-4 w-4 cursor-pointer rounded border-black/20 text-blue-600 focus:ring-blue-500">
                        </td>
                        <td class="px-4 py-2.5 text-slate-400 dark:text-slate-500">{{ $allUsers->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-2.5">
                            @include('admin.users.partials.name-email', ['user' => $user, 'showVerification' => true])
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap text-slate-500 dark:text-slate-400">
                            {{ $user->created_at->translatedFormat('d M Y') }}
                        </td>
                        <td class="px-4 py-2.5">
                            @if ($user->role !== null)
                                @php($scopeParts = $scopeDisplayPartsFor($user))
                                <div class="font-medium text-slate-900 dark:text-white">{{ $user->role->label() }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $scopeParts['label'] }}</div>
                                @if ($scopeParts['parent'])
                                    <div class="text-xs text-slate-500 dark:text-slate-400">({{ $scopeParts['parent'] }})</div>
                                @endif
                            @else
                                <span class="text-slate-400 dark:text-slate-500">{{ __('users.tab_unassigned') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            @include('admin.users.partials.status-badges', ['user' => $user])
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex flex-nowrap items-center justify-end gap-3">
                                @if ($user->role === null)
                                    {{-- "Jadikan Admin" — the only tab where a role === null row has
                                         no other path to being assigned at all (unlike "Belum
                                         Ditugaskan", which already has this same promote() form
                                         inline in every row). Opens the static #promote-modal
                                         below, filled in per-row by its own trigger data
                                         attributes rather than a per-row fetch — role/scope option
                                         lists never differ by target, only by actor. --}}
                                    <button
                                        type="button"
                                        data-promote-modal-trigger
                                        data-promote-url="{{ route('admin.users.promote', $user) }}"
                                        data-user-name="{{ $user->name }}"
                                        data-user-email="{{ $user->email }}"
                                        title="{{ __('users.promote_action') }}"
                                        aria-label="{{ __('users.promote_action') }}"
                                        class="shrink-0 cursor-pointer text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400"
                                    >
                                        <x-icon name="flag" class="h-5 w-5" />
                                    </button>
                                @elseif (auth()->user()->can('revoke', $user))
                                    {{-- "Cabut" — the same role-revoke action user-row.blade.php's
                                         own text button already offers on the Admin/Pemimpin/
                                         Institusi tabs (see UserAssignmentController::revoke()),
                                         just as an icon here to match this row's own visual
                                         language. Turns $user back into a regular (role === null)
                                         member — their region columns are kept, not cleared (see
                                         that method's own doc comment), so they still show up
                                         correctly scoped wherever a regional admin looks next. --}}
                                    <form
                                        method="POST"
                                        action="{{ route('admin.users.revoke', $user) }}"
                                        data-confirm="{{ __('users.revoke_confirm', ['name' => $user->name]) }}"
                                    >
                                        @csrf
                                        <input type="hidden" name="tab" value="all">
                                        <input type="hidden" name="all_search" value="{{ $allSearch }}">
                                        <input type="hidden" name="all_sort" value="{{ $allSort }}">
                                        @if ($allVerification !== 'all')
                                            <input type="hidden" name="all_verification" value="{{ $allVerification }}">
                                        @endif
                                        @if ($allRole !== 'all')
                                            <input type="hidden" name="all_role" value="{{ $allRole }}">
                                        @endif
                                        <button
                                            type="submit"
                                            title="{{ __('users.revoke') }}"
                                            aria-label="{{ __('users.revoke') }}"
                                            class="shrink-0 cursor-pointer text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400"
                                        >
                                            <x-icon name="arrow-right-on-rectangle" class="h-5 w-5" />
                                        </button>
                                    </form>
                                @endif
                                @include('admin.users.partials.row-actions', [
                                    'user' => $user,
                                    'tab' => 'all',
                                    'allSearch' => $allSearch,
                                    'allSort' => $allSort,
                                    'allVerification' => $allVerification,
                                    'allRole' => $allRole,
                                ])
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
                </table>
            </div>

            <div class="border-t border-black/5 px-6 py-4 dark:border-white/5">
                <x-pagination :paginator="$allUsers" />
            </div>
            @endif
        </div>

        {{-- "Jadikan Admin" — one shared modal instance for every role === null row above (see
             each trigger button's own comment): role select + scope picker share the exact same
             data-assign-role/data-assign-scope-wrapper/data-assign-scope-checkboxes markup (and
             CSS classes) as "Belum Ditugaskan"'s own inline assign form further down this page,
             so that tab's own script — document.querySelectorAll(...) over those same selectors,
             not a per-form binding — picks this modal's instance up too automatically at page
             load, no separate init needed here. Only the small trigger-opens-the-modal wiring
             below is specific to this tab. --}}
        <x-modal id="promote-modal" :title="__('users.promote_modal_title')">
            <div class="mb-5 rounded-lg bg-slate-50 px-3 py-2.5 text-sm dark:bg-slate-800">
                <p id="promote-modal-user-name" class="font-medium text-slate-900 dark:text-white"></p>
                <p id="promote-modal-user-email" class="text-xs text-slate-500 dark:text-slate-400"></p>
            </div>

            <form id="promote-modal-form" method="POST" data-disable-on-submit>
                @csrf
                <input type="hidden" name="tab" value="all">
                <input type="hidden" name="all_search" value="{{ $allSearch }}">
                <input type="hidden" name="all_sort" value="{{ $allSort }}">
                @if ($allVerification !== 'all')
                    <input type="hidden" name="all_verification" value="{{ $allVerification }}">
                @endif
                @if ($allRole !== 'all')
                    <input type="hidden" name="all_role" value="{{ $allRole }}">
                @endif

                <label class="mb-4 block">
                    <span class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('users.promote_role_label') }}</span>
                    <select name="role" required data-assign-role class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-slate-800">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="relative mb-5" data-assign-scope-wrapper data-searchable-select hidden>
                    <span class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('users.promote_region_label') }}</span>
                    <input type="hidden" name="scope_id" data-searchable-select-value>
                    <input
                        type="text"
                        data-searchable-select-search
                        autocomplete="off"
                        placeholder="{{ __('users.search_scope_placeholder') }}"
                        class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                    >
                    <ul
                        data-searchable-select-list
                        class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"
                    ></ul>
                </div>

                {{-- Admin/Pimpinan Nasional — same checkbox popover as the inline form's own
                     equivalent block (see that markup's own comment for why). --}}
                <div class="relative mb-5" data-assign-scope-checkboxes hidden>
                    <span class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('users.promote_region_label') }}</span>
                    <button
                        type="button"
                        data-scope-checkbox-toggle
                        class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-left text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                    >
                        <span data-scope-checkbox-summary>{{ __('users.select_unions') }}</span>
                    </button>
                    <div
                        data-scope-checkbox-list
                        class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full space-y-0.5 overflow-y-auto rounded-lg border border-black/10 bg-white p-2 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"
                    ></div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="cursor-pointer rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                        {{ __('users.assign') }}
                    </button>
                    <button type="button" data-app-modal-close class="cursor-pointer text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                        {{ __('common.cancel') }}
                    </button>
                </div>
            </form>
        </x-modal>

        <script>
            (function () {
                var dialog = document.getElementById('promote-modal');

                // The shared modal script (components/modal.blade.php) only ever handles ITS OWN
                // close button/backdrop click, never opening — this is the page-specific opening
                // half for this one instance (see that component's own doc comment on why).
                dialog.addEventListener('close', function () {
                    document.body.style.overflow = '';
                });

                document.addEventListener('click', function (e) {
                    var trigger = e.target.closest('[data-promote-modal-trigger]');
                    if (! trigger) return;

                    var form = document.getElementById('promote-modal-form');
                    form.action = trigger.getAttribute('data-promote-url');
                    document.getElementById('promote-modal-user-name').textContent = trigger.getAttribute('data-user-name');
                    document.getElementById('promote-modal-user-email').textContent = trigger.getAttribute('data-user-email');

                    // Resets the role select to its first option and re-triggers the role ->
                    // scope-picker cascade wired up just below (document.querySelectorAll(
                    // '[data-assign-role]') already picked this modal's own select up at page
                    // load, since it shares the same markup) — dispatching 'change' here just
                    // re-runs that existing handler instead of duplicating its logic, and its own
                    // setOptions() call already clears any previously-chosen scope back to blank
                    // as a side effect.
                    var roleSelect = form.querySelector('[data-assign-role]');
                    roleSelect.selectedIndex = 0;
                    roleSelect.dispatchEvent(new Event('change'));

                    document.body.style.overflow = 'hidden';
                    dialog.showModal();
                });
            })();
        </script>

        <script>
            var assignScopeData = @json($scopeDataByLevel);
        </script>

        {{-- The role -> scope-picker cascade for the #promote-modal's own role select above —
             previously lived in the now-removed "Belum Ditugaskan" tab (its own inline per-row
             assign form used the exact same data-assign-role/data-assign-scope-wrapper/
             data-assign-scope-checkboxes markup), moved here since this modal is now the only
             thing on the page that still needs it. Nothing about the logic itself changed. --}}
        <script>
            (function () {
                var LEVEL_LABELS = {
                    divisi: @json(__('common.division')),
                    uni: @json(__('common.union')),
                    daerah: @json(__('common.conference')),
                    gereja: @json(__('common.church')),
                    institusi: @json(__('common.institution')),
                };
                var SEARCH_SCOPE_TEMPLATE = @json(__('users.search_scope_for', ['level' => ':level']));
                var SELECT_UNIONS_LABEL = @json(__('users.select_unions'));
                var UNIONS_SELECTED_TEMPLATE = @json(__('users.unions_selected_count', ['count' => ':count']));

                // 'global' (unrestricted, e.g. Admin Global) needs no scope at all — same as
                // 'nasional' used to before it became Union-set-scoped. 'nasional' now needs the
                // checkbox popover instead of the single-value combobox every other level uses.
                function levelForRole(role) {
                    if (role.endsWith('_global')) return 'global';
                    if (role.endsWith('_nasional')) return 'nasional';
                    if (role.endsWith('_divisi')) return 'divisi';
                    if (role.endsWith('_uni')) return 'uni';
                    if (role.endsWith('_daerah')) return 'daerah';
                    if (role.endsWith('_gereja')) return 'gereja';
                    if (role.endsWith('_institusi')) return 'institusi';
                    return 'global';
                }

                function renderUnionCheckboxes(checkboxWrapper) {
                    var list = checkboxWrapper.querySelector('[data-scope-checkbox-list]');
                    if (list.dataset.rendered) return;
                    list.dataset.rendered = '1';

                    (assignScopeData.nasional || []).forEach(function (option) {
                        var label = document.createElement('label');
                        label.className = 'flex items-center gap-2 rounded px-1 py-1 hover:bg-slate-50 dark:hover:bg-slate-700';

                        var input = document.createElement('input');
                        input.type = 'checkbox';
                        input.name = 'scope_ids[]';
                        input.value = option.id;
                        input.className = 'shrink-0';
                        input.addEventListener('change', function () { updateScopeCheckboxSummary(checkboxWrapper); });

                        var span = document.createElement('span');
                        span.textContent = option.label;

                        label.appendChild(input);
                        label.appendChild(span);
                        list.appendChild(label);
                    });
                }

                function updateScopeCheckboxSummary(checkboxWrapper) {
                    var checked = checkboxWrapper.querySelectorAll('input[type=checkbox]:checked');
                    checkboxWrapper.querySelector('[data-scope-checkbox-summary]').textContent = checked.length
                        ? UNIONS_SELECTED_TEMPLATE.replace(':count', checked.length)
                        : SELECT_UNIONS_LABEL;
                }

                function refreshScope(roleSelect) {
                    var form = roleSelect.closest('form');
                    var wrapper = form.querySelector('[data-assign-scope-wrapper]');
                    var checkboxWrapper = form.querySelector('[data-assign-scope-checkboxes]');
                    var level = levelForRole(roleSelect.value);

                    if (level === 'global') {
                        wrapper.hidden = true;
                        wrapper._searchableSelect.setOptions([]);
                        checkboxWrapper.hidden = true;
                        return;
                    }

                    if (level === 'nasional') {
                        wrapper.hidden = true;
                        wrapper._searchableSelect.setOptions([]);
                        checkboxWrapper.hidden = false;
                        renderUnionCheckboxes(checkboxWrapper);
                        return;
                    }

                    wrapper.hidden = false;
                    checkboxWrapper.hidden = true;
                    wrapper._searchableSelect.setOptions(assignScopeData[level] || [], SEARCH_SCOPE_TEMPLATE.replace(':level', LEVEL_LABELS[level] || ''));
                }

                document.querySelectorAll('[data-assign-scope-wrapper]').forEach(function (wrapper) {
                    window.initSearchableSelect(wrapper);
                });

                // position:fixed with coordinates computed here (rather than the plain CSS
                // absolute/top-full this used to rely on) — every table on this app's admin
                // pages wraps in an overflow-x-auto div (see x-admin-list-card), which forces
                // overflow-y to 'auto' too per the CSS spec, clipping an absolutely-positioned
                // dropdown the moment it extends past that div's own box (confirmed happening,
                // same fix as partials/searchable-select.blade.php's positionList()).
                function positionCheckboxList(btn, list) {
                    var estimatedListHeight = 208; // mirrors the list's own max-h-52 Tailwind class
                    var btnRect = btn.getBoundingClientRect();
                    var spaceBelow = window.innerHeight - btnRect.bottom;
                    var spaceAbove = btnRect.top;
                    var openUpward = spaceBelow < estimatedListHeight && spaceAbove > spaceBelow;

                    list.style.position = 'fixed';
                    list.style.left = btnRect.left + 'px';
                    list.style.top = openUpward ? 'auto' : (btnRect.bottom + 4) + 'px';
                    list.style.bottom = openUpward ? (window.innerHeight - btnRect.top + 4) + 'px' : 'auto';
                }

                function closeAllCheckboxLists() {
                    document.querySelectorAll('[data-scope-checkbox-list]').forEach(function (list) { list.classList.add('hidden'); });
                    window.removeEventListener('scroll', closeAllCheckboxLists, true);
                }

                document.querySelectorAll('[data-scope-checkbox-toggle]').forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        var list = btn.closest('[data-assign-scope-checkboxes]').querySelector('[data-scope-checkbox-list]');
                        var opening = list.classList.contains('hidden');

                        closeAllCheckboxLists();

                        if (opening) {
                            positionCheckboxList(btn, list);
                            list.classList.remove('hidden');
                            // A fixed-position dropdown doesn't move with the page the way an
                            // absolutely-positioned one naturally would — closing on any scroll
                            // (the table's own horizontal one included, since it bubbles) avoids
                            // it drifting away from the button it belongs to.
                            window.addEventListener('scroll', closeAllCheckboxLists, true);
                        }
                    });
                });
                document.addEventListener('click', closeAllCheckboxLists);

                document.querySelectorAll('[data-assign-role]').forEach(function (select) {
                    refreshScope(select);
                    select.addEventListener('change', function () { refreshScope(select); });
                });

                // Belt-and-suspenders: a hidden input's `required` attribute is ignored by
                // browsers, so block submission here if a visible scope combobox/checkbox list
                // has no selection yet (matches the old <select required> behavior it replaced).
                document.querySelectorAll('[data-assign-role]').forEach(function (select) {
                    var form = select.closest('form');
                    form.addEventListener('submit', function (e) {
                        var wrapper = form.querySelector('[data-assign-scope-wrapper]');
                        var checkboxWrapper = form.querySelector('[data-assign-scope-checkboxes]');

                        if (! wrapper.hidden && ! wrapper._searchableSelect.getValue()) {
                            e.preventDefault();
                            wrapper.querySelector('[data-searchable-select-search]').focus();
                            return;
                        }

                        if (! checkboxWrapper.hidden && checkboxWrapper.querySelectorAll('input[type=checkbox]:checked').length === 0) {
                            e.preventDefault();
                            checkboxWrapper.querySelector('[data-scope-checkbox-toggle]').focus();
                        }
                    });
                });
            })();
        </script>

        @include('partials.bulk-select')
        <script>
            window.initBulkSelect('semua-user', 'all-bulk-form', '[data-all-user-checkbox]', '[data-select-all-all-users]', '[data-bulk-resend-otp-button], [data-bulk-deactivate-button], [data-bulk-delete-button]');
        </script>
    </div>

    @if ($canReviewSuggestions)
        <div data-tab-panel="saran" @class(['hidden' => $activeTab !== 'saran'])>
            @include('admin.users.partials.suggestions-tab')
        </div>
    @endif

    <div data-tab-panel="belum-admin" @class(['hidden' => $activeTab !== 'belum-admin'])>
        @include('admin.users.partials.no-admin-tab')
    </div>

    <div data-tab-panel="admin" @class(['hidden' => $activeTab !== 'admin'])>
        {{-- Merges the old separate Admin/Pemimpin/Institusi tabs into one filterable, paginated
             list, per the user's explicit call — same filter model as "Semua User" (search +
             role + sort), plus a Uni -> Daerah cascading region filter (same partial "Belum Ada
             Admin" already uses) since every row here has a real region, unlike "Semua User". --}}
        <x-filter-card :clear-url="($staffSearch !== '' || $staffRole !== 'all' || $staffSelectedUnionId || $staffSelectedConferenceId || $staffSelectedInstitutionId || $staffSort !== 'name_asc') ? route('admin.users.index', ['tab' => 'admin']) : null">
            <form method="GET" id="staff-filter-form" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
                <label class="relative block w-full max-w-sm flex-1">
                    <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="search"
                        name="staff_search"
                        value="{{ $staffSearch }}"
                        placeholder="{{ __('users.search_placeholder') }}"
                        class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                    >
                </label>
                <label class="relative min-w-[200px]">
                    <select
                        name="staff_role"
                        onchange="this.form.submit()"
                        class="w-full appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-4 text-sm font-medium text-slate-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <option value="all" @selected($staffRole === 'all')>{{ __('users.filter_role_all') }}</option>
                        @foreach ($staffRoleOptions as $roleOption)
                            <option value="{{ $roleOption->value }}" @selected($staffRole === $roleOption->value)>{{ $roleOption->label() }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>

                @include('partials.analytics-region-filter', [
                    'prefix' => 'staff',
                    'formId' => 'staff-filter-form',
                    'isNasionalView' => $isNasionalView,
                    'isUniView' => $isUniView,
                    'unionOptions' => $staffUnionOptions,
                    'conferenceOptions' => $staffConferenceOptions,
                    'selectedUnionId' => $staffSelectedUnionId,
                    'selectedConferenceId' => $staffSelectedConferenceId,
                    'unionFieldName' => 'staff_union_id',
                    'conferenceFieldName' => 'staff_conference_id',
                ])

                @if ($canManageInstitutions)
                    {{-- Institusi sits outside the Divisi/Uni/Daerah/Gereja tree entirely (no
                         Union tie at all), so it gets its own separate filter rather than folding
                         into the Uni/Daerah cascade above. --}}
                    <label class="relative min-w-[200px]">
                        <select
                            name="staff_institution_id"
                            onchange="this.form.submit()"
                            class="w-full appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-4 text-sm font-medium text-slate-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <option value="" @selected(! $staffSelectedInstitutionId)>{{ __('users.filter_institution_all') }}</option>
                            @foreach ($staffInstitutionOptions as $institutionOption)
                                <option value="{{ $institutionOption->id }}" @selected((string) $staffSelectedInstitutionId === (string) $institutionOption->id)>{{ $institutionOption->name }}</option>
                            @endforeach
                        </select>
                        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    </label>
                @endif

                <label class="relative min-w-[200px]">
                    <select
                        name="staff_sort"
                        onchange="this.form.submit()"
                        class="w-full appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-4 text-sm font-medium text-slate-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <option value="name_asc" @selected($staffSort === 'name_asc')>{{ __('users.sort_name_asc') }}</option>
                        <option value="name_desc" @selected($staffSort === 'name_desc')>{{ __('users.sort_name_desc') }}</option>
                        <option value="region_asc" @selected($staffSort === 'region_asc')>{{ __('users.sort_region_asc') }}</option>
                        <option value="region_desc" @selected($staffSort === 'region_desc')>{{ __('users.sort_region_desc') }}</option>
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>
            </form>
        </x-filter-card>

        {{-- Same "Data Per Divisi/Uni/Daerah" card shape as "Semua User"'s own copy — read-only,
             no Status/Aksi columns, per the earlier "just a list" call this tab already followed
             before the merge. --}}
        <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('users.tab_admin') }}</h2>
            </div>

            @if ($staffUsers->isEmpty())
                <div class="border-t border-black/5 px-6 py-8 dark:border-white/5">
                    <x-empty-state variant="inline">{{ __('users.no_admin_yet') }}</x-empty-state>
                </div>
            @else
            <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                <table class="w-full text-left text-sm">
            <thead>
                <tr class="bg-slate-50 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                    <th class="px-4 py-2.5 font-semibold">#</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('users.col_user') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('users.col_role_scope') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($staffUsers as $user)
                    <tr>
                        <td class="px-4 py-2.5 text-slate-400 dark:text-slate-500">{{ $staffUsers->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-2.5">
                            @include('admin.users.partials.name-email', ['user' => $user])
                        </td>
                        <td class="px-4 py-2.5">
                            @php($scopeParts = $scopeDisplayPartsFor($user))
                            <div class="font-medium text-slate-900 dark:text-white">{{ $user->role->label() }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $scopeParts['label'] }}</div>
                            @if ($scopeParts['parent'])
                                <div class="text-xs text-slate-500 dark:text-slate-400">({{ $scopeParts['parent'] }})</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
                </table>
            </div>

            <div class="border-t border-black/5 px-6 py-4 dark:border-white/5">
                <x-pagination :paginator="$staffUsers" />
            </div>
            @endif
        </div>
    </div>

    @if ($isSuperAdmin)
        <div data-tab-panel="terhapus" @class(['hidden' => $activeTab !== 'terhapus'])>
            <x-admin-list-card :items="$trashedUsers" :title="__('users.trashed_title')" :subtitle="__('users.trashed_subtitle')" :empty-message="__('users.no_trashed')" :paginated="false">
                <thead>
                    <tr class="bg-slate-50 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                        <th class="py-2 pr-2 font-semibold">#</th>
                        <th class="py-2 pr-2 font-semibold">{{ __('users.col_user') }}</th>
                        <th class="py-2 pr-2 font-semibold">{{ __('users.col_role') }}</th>
                        <th class="py-2 pr-2 font-semibold">{{ __('users.col_scope') }}</th>
                        <th class="py-2 pr-2 font-semibold">{{ __('users.col_deleted_at') }}</th>
                        <th class="py-2 text-right font-semibold">{{ __('common.action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($trashedUsers as $user)
                        <tr>
                            <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $loop->iteration }}</td>
                            <td class="py-2 pr-2">
                                @include('admin.users.partials.name-email', ['user' => $user])
                            </td>
                            <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $user->role?->label() ?? '—' }}</td>
                            <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">
                                {{ $user->church?->name ?? $user->conference?->name ?? $user->union?->name ?? $user->division?->name ?? $user->institution?->name ?? ($user->assignedUnions->isNotEmpty() ? $user->assignedUnions->pluck('name')->implode(', ') : '—') }}
                            </td>
                            <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $user->deleted_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="py-2">
                                <div class="flex flex-nowrap items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('admin.users.restore', $user) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            title="{{ __('users.restore') }}"
                                            aria-label="{{ __('users.restore') }}"
                                            class="shrink-0 text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400"
                                        >
                                            <x-icon name="arrow-path" class="h-5 w-5" />
                                        </button>
                                    </form>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.users.force-delete', $user) }}"
                                        data-confirm="{{ __('users.force_delete_confirm', ['name' => $user->name]) }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            title="{{ __('users.force_delete') }}"
                                            aria-label="{{ __('users.force_delete') }}"
                                            class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            <x-icon name="trash" class="h-5 w-5" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-admin-list-card>
        </div>
    @endif

    @if ($noAdminTotal > 0)
        @include('partials.analytics-group-toggle', ['expandGroupsByDefault' => true])
    @endif

    @include('partials.tab-script', ['activeTab' => $activeTab])

    {{-- Opens row-actions.blade.php's own Edit icon into this instead of navigating to a
         separate page — see the partial's own doc comment for the full contract (same one
         Kelola Akun's own edit modal uses). --}}
    @include('partials.entity-edit-modal')
@endsection
