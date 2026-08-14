{{--
    Two independent floating controls, bottom-right on every page:
    - A "back to top" button that only appears once the page is scrolled down.
    - A Customer Service bubble linking out to WhatsApp/Messenger — always includes the Global
      Coordinator (superadmin-configured in Pengaturan, see AppSetting::csWhatsappUrl()) and
      every Global chat group (see CoordinatorGroup, union_id null), plus:
        - a superadmin/admin_global sees EVERY active Union's own coordinator/group(s) that are
          actually set (Kelola Akun → Uni), since they oversee all of them;
        - a scoped admin_nasional sees just their own assigned Unions' coordinators/group(s);
        - admin_divisi sees every active Union under their own Division;
        - anyone scoped to a single Union (admin_uni/daerah/gereja) sees just their own Union's,
          if it has any set.
      A guest/plain member/institusi-level viewer has no Union of their own (institutions sit
      outside the global→nasional→divisi→uni→daerah→gereja chain, see UserRole::level()), so
      they only ever see the global entries. Renders nothing at all if there's nothing to show,
      rather than a dead button.

    Stacked at bottom-6/bottom-24 to leave the layout's #refresh-progress-widget (a temporary
    card only shown during an active bulk refresh, see layouts/app.blade.php) room to sit above
    both without overlapping them.
--}}
@php
    $floatingWidgetUser = auth()->user();
    $hasGlobalCsAccess = $floatingWidgetUser?->role?->hasGlobalAccess() ?? false;
    $isScopedNasionalCsAdmin = $floatingWidgetUser?->role?->level() === 'nasional';
    $isDivisiCsAdmin = $floatingWidgetUser?->role?->level() === 'divisi';

    $floatingWidgetUnion = match ($floatingWidgetUser?->role?->level()) {
        'uni' => $floatingWidgetUser->union,
        'daerah' => $floatingWidgetUser->conference?->union,
        'gereja' => $floatingWidgetUser->church?->conference?->union,
        default => null,
    };

    $floatingWidgetSettings = \App\Models\AppSetting::current();
    $csEntries = collect();

    if ($url = $floatingWidgetSettings->csWhatsappUrl()) {
        $csEntries->push(['url' => $url, 'label' => __('common.cs_whatsapp_link_global'), 'icon' => 'chat-bubble']);
    }
    foreach (\App\Models\CoordinatorGroup::whereNull('union_id')->get() as $group) {
        $csEntries->push(['url' => $group->url, 'label' => __('common.cs_group_link_global', ['platform' => $group->platform->label()]), 'icon' => 'users']);
    }

    $floatingWidgetUnions = match (true) {
        $hasGlobalCsAccess => \App\Models\Union::where('is_active', true)->orderBy('name')->with('groups')->get(),
        $isScopedNasionalCsAdmin => \App\Models\Union::whereIn('id', $floatingWidgetUser->assignedUnionIds())->where('is_active', true)->orderBy('name')->with('groups')->get(),
        $isDivisiCsAdmin => \App\Models\Union::where('division_id', $floatingWidgetUser->division_id)->where('is_active', true)->orderBy('name')->with('groups')->get(),
        default => collect([$floatingWidgetUnion])->filter(),
    };

    foreach ($floatingWidgetUnions as $union) {
        if ($url = $union->coordinatorWhatsappUrl()) {
            $csEntries->push(['url' => $url, 'label' => __('common.cs_whatsapp_link_union', ['union' => $union->name]), 'icon' => 'chat-bubble']);
        }
        foreach ($union->groups as $group) {
            $csEntries->push(['url' => $group->url, 'label' => __('common.cs_group_link_union', ['platform' => $group->platform->label(), 'union' => $union->name]), 'icon' => 'users']);
        }
    }
@endphp

@if ($csEntries->isNotEmpty())
    <div class="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-3" data-cs-widget>
        <div data-cs-panel class="hidden max-h-80 w-64 overflow-y-auto rounded-2xl border border-black/5 bg-white p-2 shadow-lg dark:border-white/5 dark:bg-slate-900">
            @foreach ($csEntries as $entry)
                <a
                    href="{{ $entry['url'] }}" target="_blank" rel="noopener"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#DF753A]/10 text-[#DF753A] dark:bg-[#DF753A]/20">
                        <x-icon name="{{ $entry['icon'] }}" class="h-5 w-5" />
                    </span>
                    {{ $entry['label'] }}
                </a>
            @endforeach
        </div>

        <button
            type="button"
            data-cs-toggle
            aria-label="{{ __('common.customer_service') }}"
            title="{{ __('common.customer_service') }}"
            class="flex h-14 w-14 items-center justify-center rounded-full bg-[#DF753A] text-white shadow-lg transition hover:bg-[#DF753A]/90"
        >
            <x-icon name="chat-bubble" class="h-6 w-6" />
        </button>
    </div>
@endif

<button
    type="button"
    data-back-to-top
    aria-label="{{ __('common.back_to_top') }}"
    title="{{ __('common.back_to_top') }}"
    class="fixed bottom-24 right-6 z-40 hidden h-11 w-11 items-center justify-center rounded-full bg-white text-slate-600 shadow-lg ring-1 ring-black/5 transition hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:ring-white/10 dark:hover:bg-slate-700"
>
    <x-icon name="chevron-up" class="h-5 w-5" />
</button>

<script>
    (function () {
        var backToTop = document.querySelector('[data-back-to-top]');
        if (backToTop) {
            var toggleVisibility = function () {
                var shouldShow = window.scrollY > 400;
                backToTop.classList.toggle('hidden', !shouldShow);
                backToTop.classList.toggle('flex', shouldShow);
            };
            window.addEventListener('scroll', toggleVisibility, { passive: true });
            toggleVisibility();
            backToTop.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        var csWidget = document.querySelector('[data-cs-widget]');
        if (csWidget) {
            var csToggle = csWidget.querySelector('[data-cs-toggle]');
            var csPanel = csWidget.querySelector('[data-cs-panel]');

            csToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                csPanel.classList.toggle('hidden');
            });

            document.addEventListener('click', function (e) {
                if (!csWidget.contains(e.target)) csPanel.classList.add('hidden');
            });
        }
    })();
</script>
