{{-- One social account's weekly stat history table (with heading) — shared by churches/show,
     institutions/show, and people/show. Self-guards on empty history, so the caller can just
     loop every social without its own isNotEmpty() check. $categoryLabel is the "(Akun Gereja)"
     suffix churches.show adds to disambiguate a church's gereja/umum accounts — null everywhere
     else, since only Church has that category split. --}}
@props(['social', 'historyRows', 'categoryLabel' => null])

@php
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'x' => 'X'];
    $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count', 'x' => 'followers_count'];
    $secondaryFields = [
        'youtube' => ['views_count' => 'Views', 'videos_count' => 'Videos'],
        'instagram' => ['following_count' => 'Following', 'posts_count' => 'Posts'],
        'tiktok' => ['following_count' => 'Following', 'likes_count' => 'Likes', 'posts_count' => 'Posts'],
        'facebook' => ['recent_posts_count' => 'Posts'],
        'x' => ['following_count' => 'Following', 'posts_count' => 'Posts'],
    ];
@endphp

@if ($historyRows->isNotEmpty())
    <div class="mt-8">
        <h2 class="mb-3 flex items-center gap-2 font-medium">
            <x-platform-icon :platform="$social->platform" class="h-6 w-6 text-xs" />
            {{ __('entity.history_heading', ['platform' => $platformLabels[$social->platform->value]]) }}
            <span class="font-normal text-slate-500 dark:text-slate-400">{{ $social->display_handle }}</span>
            @if ($categoryLabel)
                <span class="text-sm font-normal text-slate-400 dark:text-slate-500">({{ $categoryLabel }})</span>
            @endif
        </h2>
        <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">{{ __('common.date') }}</th>
                        <th class="px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">
                            {{ $social->platform === \App\Enums\SocialPlatform::YouTube ? 'Subscribers' : 'Followers' }}
                        </th>
                        @foreach ($secondaryFields[$social->platform->value] as $label)
                            <th class="px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                    @foreach ($historyRows as $row)
                        <tr>
                            <td class="px-4 py-2.5 tabular-nums">{{ $row->recorded_at->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-2.5 font-medium tabular-nums">{{ number_format($row->{$countField[$social->platform->value]} ?? 0) }}</td>
                            @foreach ($secondaryFields[$social->platform->value] as $secField => $label)
                                <td class="px-4 py-2.5 tabular-nums">{{ number_format($row->{$secField} ?? 0) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
