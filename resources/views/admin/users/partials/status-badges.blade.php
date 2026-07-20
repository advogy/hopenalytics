<div class="flex flex-col items-start gap-1.5">
    @if ($user->hasVerifiedEmail())
        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
            Terverifikasi
        </span>
    @else
        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-400">
            Menunggu Verifikasi
        </span>
    @endif

    @if ($user->is_active)
        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            Aktif
        </span>
    @else
        <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-400">
            Nonaktif
        </span>
    @endif
</div>
