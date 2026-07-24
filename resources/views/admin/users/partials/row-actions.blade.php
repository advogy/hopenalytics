{{--
    Expects: $user, $tab (which tab panel this row lives in — unassigned/admin/pemimpin/
    institusi — so the controller can redirect back to the same tab instead of whatever tab
    happened to be in the URL when the page was first loaded; tab-switching here is client-side
    only, see partials/tab-script.blade.php, so the URL never reflects the tab actually visible).
--}}
<div class="flex flex-nowrap items-center justify-end gap-3">
    @unless ($user->hasVerifiedEmail())
        <form method="POST" action="{{ route('admin.users.resend-otp', $user) }}" data-disable-on-submit>
            @csrf
            <input type="hidden" name="tab" value="{{ $tab }}">
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
