{{--
    Expects: $user, $tab (which tab panel this row lives in — unassigned/admin/pemimpin/
    institusi — so the controller can redirect back to the same tab instead of whatever tab
    happened to be in the URL when the page was first loaded; tab-switching here is client-side
    only, see partials/tab-script.blade.php, so the URL never reflects the tab actually visible).

    Every action below is excluded for one's own row by UserPolicy::manageable() (you can't
    edit/deactivate/delete/resend-OTP yourself from here) — so when $user is the viewer
    themselves, none of these buttons would ever do anything but 403; show a plain label
    instead of icons that look actionable but always fail.
--}}
@if (auth()->user()->is($user))
    <p class="text-right text-xs text-slate-400 dark:text-slate-500">{{ __('users.this_is_you') }}</p>
@else
    <div class="flex flex-nowrap items-center justify-end gap-3">
        <a
            href="{{ route('admin.users.edit', ['target' => $user, 'tab' => $tab]) }}"
            title="{{ __('common.edit') }}"
            aria-label="{{ __('common.edit') }}"
            class="shrink-0 text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
        >
            <x-icon name="pencil-square" class="h-5 w-5" />
        </a>

        @can('releaseRegion', $user)
            @if ($user->division_id || $user->union_id || $user->conference_id || $user->church_id)
                @php
                    $hasScopedActiveRole = $user->role !== null && in_array($user->role->level(), ['divisi', 'uni', 'daerah', 'gereja'], true);
                    $releaseRegionConfirm = $hasScopedActiveRole
                        ? __('users.release_region_confirm_active_role', ['name' => $user->name, 'role' => $user->role->label()])
                        : __('users.release_region_confirm', ['name' => $user->name]);
                @endphp
                <form method="POST" action="{{ route('admin.users.release-region', $user) }}" data-confirm="{{ $releaseRegionConfirm }}">
                    @csrf
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    {{-- $search/$sort: only ever passed for the 'unassigned' tab (its own filter
                         state — see index.blade.php's own include call). Without these, every
                         action below used to bounce back to a blank, unfiltered/unsorted
                         unassigned list regardless of what was actually showing, since
                         redirectToTab() only ever preserved $tab. --}}
                    @isset($search)
                        <input type="hidden" name="search" value="{{ $search }}">
                    @endisset
                    @isset($sort)
                        <input type="hidden" name="sort" value="{{ $sort }}">
                    @endisset
                    <button
                        type="submit"
                        title="{{ __('users.release_region') }}"
                        aria-label="{{ __('users.release_region') }}"
                        class="shrink-0 text-slate-500 hover:text-amber-600 dark:text-slate-400 dark:hover:text-amber-400"
                    >
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </form>
            @endif
        @endcan

        @unless ($user->hasVerifiedEmail())
            <form method="POST" action="{{ route('admin.users.resend-otp', $user) }}" data-disable-on-submit>
                @csrf
                <input type="hidden" name="tab" value="{{ $tab }}">
            {{-- $search/$sort — see the release-region form above for why these are here. --}}
            @isset($search)
                <input type="hidden" name="search" value="{{ $search }}">
            @endisset
            @isset($sort)
                <input type="hidden" name="sort" value="{{ $sort }}">
            @endisset
                <button
                    type="submit"
                    title="{{ __('users.resend_otp') }}"
                    aria-label="{{ __('users.resend_otp') }}"
                    class="shrink-0 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <x-icon name="arrow-path" class="h-5 w-5" />
                </button>
            </form>
        @endunless

        <form
            method="POST"
            action="{{ route('admin.users.toggle-active', $user) }}"
            @if ($user->is_active) data-confirm="{{ __('users.deactivate_user_confirm', ['name' => $user->name]) }}" @endif
        >
            @csrf
            <input type="hidden" name="tab" value="{{ $tab }}">
            {{-- $search/$sort — see the release-region form above for why these are here. --}}
            @isset($search)
                <input type="hidden" name="search" value="{{ $search }}">
            @endisset
            @isset($sort)
                <input type="hidden" name="sort" value="{{ $sort }}">
            @endisset
            <button
                type="submit"
                title="{{ $user->is_active ? __('accounts.deactivate') : __('accounts.activate') }}"
                aria-label="{{ $user->is_active ? __('accounts.deactivate') : __('accounts.activate') }}"
                class="shrink-0 {{ $user->is_active ? 'text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400' : 'text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400' }}"
            >
                <x-icon name="{{ $user->is_active ? 'x-circle' : 'check-circle' }}" class="h-5 w-5" />
            </button>
        </form>

        <form
            method="POST"
            action="{{ route('admin.users.destroy', $user) }}"
            data-confirm="{{ __('users.delete_user_confirm', ['name' => $user->name]) }}"
        >
            @csrf
            @method('DELETE')
            <input type="hidden" name="tab" value="{{ $tab }}">
            {{-- $search/$sort — see the release-region form above for why these are here. --}}
            @isset($search)
                <input type="hidden" name="search" value="{{ $search }}">
            @endisset
            @isset($sort)
                <input type="hidden" name="sort" value="{{ $sort }}">
            @endisset
            <button
                type="submit"
                title="{{ __('common.delete') }}"
                aria-label="{{ __('common.delete') }}"
                class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
            >
                <x-icon name="trash" class="h-5 w-5" />
            </button>
        </form>
    </div>
@endif
