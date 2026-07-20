<div class="flex flex-nowrap items-center justify-end gap-3">
    @unless ($user->hasVerifiedEmail())
        <form method="POST" action="{{ route('admin.users.resend-otp', $user) }}">
            @csrf
            <button
                type="submit"
                title="Kirim Ulang OTP"
                aria-label="Kirim Ulang OTP"
                class="shrink-0 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
            >
                <x-icon name="arrow-path" class="h-5 w-5" />
            </button>
        </form>
    @endunless

    <form
        method="POST"
        action="{{ route('admin.users.toggle-active', $user) }}"
        @if ($user->is_active) data-confirm="Nonaktifkan &quot;{{ $user->name }}&quot;? Mereka tidak akan bisa login sampai diaktifkan kembali." @endif
    >
        @csrf
        <button
            type="submit"
            title="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
            aria-label="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
            class="shrink-0 {{ $user->is_active ? 'text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400' : 'text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400' }}"
        >
            <x-icon name="{{ $user->is_active ? 'x-circle' : 'check-circle' }}" class="h-5 w-5" />
        </button>
    </form>

    <form
        method="POST"
        action="{{ route('admin.users.destroy', $user) }}"
        data-confirm="Hapus &quot;{{ $user->name }}&quot;? Akun ini akan hilang dari semua daftar."
    >
        @csrf
        @method('DELETE')
        <button
            type="submit"
            title="Hapus"
            aria-label="Hapus"
            class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
        >
            <x-icon name="trash" class="h-5 w-5" />
        </button>
    </form>
</div>
