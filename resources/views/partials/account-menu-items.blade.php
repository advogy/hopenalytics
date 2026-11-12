{{--
    The account menu: everything under the user's name at the top right (desktop dropdown and the
    bottom of the mobile menu both include this). Admin pages are grouped into tabbed hubs (see
    App\Support\AdminHubs), so each hub is one entry here.
--}}
@php
    $user = auth()->user();
    $hubs = collect(['accounts', 'goals', 'settings', 'monitoring'])
        ->mapWithKeys(fn ($hub) => [$hub => \App\Support\AdminHubs::entry($hub, $user)]);
    $hubIcons = ['accounts' => 'building-office', 'goals' => 'flag', 'settings' => 'cog-6-tooth', 'monitoring' => 'queue-list'];

    $manageItems = array_filter([
        $user->can('delegate-users') ? ['label' => __('nav.manage_users'), 'url' => route('admin.users.index'), 'icon' => 'users', 'badge' => 0] : null,
        $hubs['accounts'] ? $hubs['accounts'] + ['icon' => $hubIcons['accounts']] : null,
        $hubs['goals'] ? $hubs['goals'] + ['icon' => $hubIcons['goals']] : null,
        $user->can('send-bulk-email') ? ['label' => __('email_broadcasts.nav_label'), 'url' => route('admin.email-broadcasts.index'), 'icon' => 'envelope', 'badge' => 0] : null,
    ]);
    $systemItems = array_filter([
        $hubs['settings'] ? $hubs['settings'] + ['icon' => $hubIcons['settings']] : null,
        $hubs['monitoring'] ? $hubs['monitoring'] + ['icon' => $hubIcons['monitoring']] : null,
    ]);
@endphp

<a href="{{ route('profile.edit') }}" class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
    <span class="flex items-center gap-2">
        <x-icon name="user" class="h-4 w-4 shrink-0 text-slate-400" />
        {{ __('nav.my_profile') }}
    </span>
    <x-role-badge :role="auth()->user()->role" />
</a>
@if (auth()->user()->church_id)
    {{-- admin_gereja's only guaranteed path to their own church(es) — Kelola Akun has no
         organization tab for gereja-level, and the map/growth widgets on the dashboard only
         surface it if it happens to have coordinates or growth data yet. One entry per church
         they manage (see User::assignedChurchIds()), named when there's more than one. --}}
    @php
        $myChurches = \App\Models\Church::query()
            ->whereIn('id', auth()->user()->assignedChurchIds() ?: [auth()->user()->church_id])
            ->orderByRaw('id = ? desc', [auth()->user()->church_id])->orderBy('name')
            ->get(['id', 'name', 'slug']);
    @endphp
    @foreach ($myChurches as $myChurch)
        <a href="{{ route('churches.show', $myChurch) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
            <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
            <span class="truncate">{{ $myChurches->count() > 1 ? $myChurch->name : __('nav.my_church') }}</span>
        </a>
    @endforeach
@endif
@if (auth()->user()->division && auth()->user()->can('update', auth()->user()->division))
    {{-- Same gap as Gereja Saya, for admin_divisi: Kelola Akun hides
         the Divisi tab from admin_divisi themselves (each level skips
         editing its own entity's identity there), so this is their only
         path to their own Division's Kelola Akun. can:update (not just
         division_id being set) keeps this from dead-ending for
         pimpinan_divisi. --}}
    <a href="{{ route('admin.divisions.socials.index', auth()->user()->division) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
        <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
        {{ __('nav.my_division') }}
    </a>
@endif
@if (auth()->user()->union && auth()->user()->can('update', auth()->user()->union))
    {{-- Same gap as Gereja Saya, for admin_uni: Kelola Akun hides
         the Uni tab from admin_uni themselves (each level skips editing
         its own entity's identity there), so this is their only path to
         their own Union's Kelola Akun. can:update (not just union_id
         being set) keeps this from dead-ending for pimpinan_uni. --}}
    <a href="{{ route('admin.unions.socials.index', auth()->user()->union) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
        <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
        {{ __('nav.my_union') }}
    </a>
@endif
@if (auth()->user()->conference && auth()->user()->can('update', auth()->user()->conference))
    <a href="{{ route('admin.conferences.socials.index', auth()->user()->conference) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
        <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
        {{ __('nav.my_conference') }}
    </a>
@endif
@if (auth()->user()->institution)
    {{-- Same as Gereja Saya: goes to the read-only institutions.show page (not
         straight to Kelola Akun), presence-only gated since pimpinan_institusi
         should see it too — just read-only, same as churches.show. --}}
    <a href="{{ route('institutions.show', auth()->user()->institution) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
        <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
        {{ __('nav.my_institution') }}
    </a>
@endif

@foreach (['nav.section_manage' => $manageItems, 'nav.section_system' => $systemItems] as $heading => $items)
    @if ($items !== [])
        <div class="my-1 border-t border-black/5 dark:border-white/5"></div>
        <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __($heading) }}</p>
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0 text-slate-400" />
                <span class="flex-1">{{ $item['label'] }}</span>
                @if ($item['badge'] > 0)
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    @endif
@endforeach

<div class="my-1 border-t border-black/5 dark:border-white/5"></div>
<a href="{{ route('about') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
    <x-icon name="information-circle" class="h-4 w-4 shrink-0 text-slate-400" />
    {{ __('nav.about') }}
</a>
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
        <x-icon name="arrow-right-on-rectangle" class="h-4 w-4 shrink-0 text-slate-400" />
        {{ __('nav.logout') }}
    </button>
</form>
