{{--
    $showVerification (default false, opt-in — the 4 pre-existing call sites of this partial
    render the email plain, unchanged): a small icon next to the email marking whether it's
    verified, for the "Semua User" tab where that's not already shown by its own "Status" column
    the way the other tabs' status-badges partial does.
--}}
<div class="font-medium text-slate-900 dark:text-white">{{ $user->name }}</div>
<div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
    <span>{{ $user->email }}</span>
    @if ($showVerification ?? false)
        @if ($user->hasVerifiedEmail())
            <x-icon name="check-circle" class="h-3.5 w-3.5 shrink-0 text-emerald-500" title="{{ __('users.verified') }}" />
        @else
            <x-icon name="clock" class="h-3.5 w-3.5 shrink-0 text-amber-500" title="{{ __('users.pending_verification') }}" />
        @endif
    @endif
</div>
