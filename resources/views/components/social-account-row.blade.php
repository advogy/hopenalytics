{{-- One row in a social-account list: platform icon, handle (linked externally when
     resolvable), manual-data badge, edit link, delete-with-confirm form. Shared by every
     "Kelola Akun" list (churches/people/organization social-list, and Profil Saya's Media
     Sosial tab) so a future tweak only needs to happen once. $padding differs only because
     Profil Saya's tab nests the list inside an already-padded card (-mx-6 trick) while the
     others use a borderless full-bleed wrapper. --}}
@props(['social', 'padding' => 'px-5 py-3.5'])

@php $externalUrl = $social->externalUrl(); @endphp

<li class="flex items-center justify-between gap-3 {{ $padding }}">
    <div class="flex min-w-0 items-center gap-3">
        <x-platform-icon :platform="$social->platform" class="h-6 w-6 shrink-0" />
        <div class="min-w-0">
            @if ($externalUrl)
                <a href="{{ $externalUrl }}" target="_blank" rel="noopener" class="truncate font-medium hover:text-blue-600 dark:hover:text-blue-400">
                    {{ $social->display_handle }}
                </a>
            @else
                <p class="truncate font-medium">{{ $social->display_handle }}</p>
            @endif
        </div>
        @unless ($social->is_auto_fetch)
            <x-icon name="pencil-square" class="h-3.5 w-3.5 shrink-0 text-amber-500" title="{{ __('directory.manual_badge_title') }}" />
        @endunless
    </div>

    <div class="flex shrink-0 items-center gap-1">
        <a
            href="{{ route('socials.edit', $social) }}"
            title="{{ __('entity.edit_account_title') }}"
            class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-blue-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-400"
        >
            <x-icon name="pencil-square" class="h-4 w-4" />
        </a>
        <form
            method="POST"
            action="{{ route('socials.destroy', $social) }}"
            data-confirm="{{ __('entity.delete_account_confirm', ['handle' => $social->display_handle]) }}"
        >
            @csrf
            @method('DELETE')
            <button
                type="submit"
                title="{{ __('entity.delete_account') }}"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-red-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-red-400"
            >
                <x-icon name="trash" class="h-4 w-4" />
            </button>
        </form>
    </div>
</li>
