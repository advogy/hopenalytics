{{--
    One row of the Direktori Akun "Gereja" tab table — extracted so its flat and Uni/Daerah-
    grouped rendering branches share the same markup.

    Expected: $church, $ancestors (optional, space-separated group ids this row is nested under).
--}}
@php $socialsByCategory = $church->socials->groupBy(fn ($s) => $s->category->value); @endphp
<tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40" @if ($ancestors ?? null) data-group-ancestors="{{ $ancestors }}" @endif>
    <td class="px-4 py-3">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-xs font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
                {{ mb_substr($church->name, 0, 1) }}
            </span>
            <div class="min-w-0">
                <p class="font-medium">{{ $church->name }}</p>
                @if ($church->city)
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $church->city }}</p>
                @endif
            </div>
        </div>
    </td>
    @foreach (['gereja', 'umum'] as $category)
        <td class="px-4 py-3">
            <div class="space-y-1.5">
                @forelse ($socialsByCategory->get($category, collect()) as $social)
                    @php $externalUrl = $social->externalUrl(); @endphp
                    <div class="flex items-center gap-2">
                        @if ($externalUrl)
                            {{-- The handle always opens the real profile (so anyone can follow it) — editing is a separate action below, not overloaded onto this link. externalUrl() falls back to a platform+handle-derived URL when profile_url was never filled in (the common case). --}}
                            <a href="{{ $externalUrl }}" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-blue-600 dark:hover:text-blue-400">
                                <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                <span>{{ $social->display_handle }}</span>
                            </a>
                        @else
                            <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                                <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                <span>{{ $social->display_handle }}</span>
                            </span>
                        @endif
                        @unless ($social->is_auto_fetch || $social->platform === \App\Enums\SocialPlatform::Facebook)
                            <x-icon name="pencil-square" class="h-3.5 w-3.5 shrink-0 text-amber-500" title="{{ __('directory.manual_badge_title') }}" />
                        @endunless
                    </div>
                @empty
                    <span class="text-slate-300 dark:text-slate-600">—</span>
                @endforelse
            </div>
        </td>
    @endforeach
</tr>
