{{--
    One row of the Direktori Akun "Divisi/Uni/Daerah" tab table — same shape as
    directory-institution-row.blade.php, except $organization is a Division, Union, OR Conference
    model.

    Expected: $organization, $ancestors (optional, space-separated group ids this row is nested under).
--}}
@php
    $organizationUrl = match (true) {
        $organization instanceof \App\Models\Division => route('divisions.show', $organization),
        $organization instanceof \App\Models\Union => route('unions.show', $organization),
        default => route('conferences.show', $organization),
    };
    $namePaddingClass = match ($depth ?? 0) {
        1 => 'pl-8',
        2 => 'pl-12',
        default => 'pl-4',
    };
@endphp
<tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40" @if ($ancestors ?? null) data-group-ancestors="{{ $ancestors }}" @endif>
    <td class="{{ $namePaddingClass }} pr-4 py-3">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-xs font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
                {{ mb_substr($organization->name, 0, 1) }}
            </span>
            <div class="min-w-0">
                <a href="{{ $organizationUrl }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400">{{ $organization->name }}</a>
                <p class="text-xs text-slate-400 dark:text-slate-500">
                    {{ match (true) {
                        $organization instanceof \App\Models\Division => __('analytics.organization_level_division'),
                        $organization instanceof \App\Models\Union => __('analytics.organization_level_union'),
                        default => __('analytics.organization_level_conference'),
                    } }}
                </p>
            </div>
        </div>
    </td>
    <td class="px-4 py-3">
        <div class="space-y-1.5">
            @forelse ($organization->socials as $social)
                @php $externalUrl = $social->externalUrl(); @endphp
                <div class="flex items-center gap-2">
                    @if ($externalUrl)
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
</tr>
