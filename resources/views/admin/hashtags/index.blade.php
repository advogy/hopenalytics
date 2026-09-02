@extends('layouts.app')

@section('title', __('hashtag.admin_title') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('hashtag.admin_title') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('hashtag.admin_subtitle') }}</p>
        </div>
        {{-- On-demand scan — e.g. checking in hour by hour during a coordinated hashtag launch —
             separate from the once-a-week auto-fetch every other tracked hashtag rides along on.
             Costs real Apify credits/YouTube quota per account scanned, hence the confirm. --}}
        <form method="POST" action="{{ route('admin.hashtags.rescan') }}" data-confirm="{{ __('hashtag.rescan_confirm') }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                <x-icon name="arrow-path" class="h-4 w-4 shrink-0" />
                {{ __('hashtag.rescan_button') }}
            </button>
        </form>
    </div>

    <div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <h2 class="mb-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('hashtag.add_title') }}</h2>
        <form method="POST" action="{{ route('admin.hashtags.store') }}" class="flex flex-wrap items-start gap-3">
            @csrf
            <div class="flex-1">
                <label class="relative block w-full sm:w-72">
                    <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-400">#</span>
                    <input
                        type="text"
                        name="tag"
                        placeholder="{{ __('hashtag.tag_placeholder') }}"
                        class="w-full rounded-lg border border-black/10 bg-white py-2 pr-3 pl-7 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                    >
                </label>
                @error('tag')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ __('hashtag.add_button') }}
            </button>
        </form>
    </div>

    <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
        @if ($hashtags->isEmpty())
            <div class="p-12 text-center">
                <p class="text-slate-500 dark:text-slate-400">{{ __('hashtag.none_tracked') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_tag') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_post_count') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_status') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('hashtag.col_added') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($hashtags as $hashtag)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $hashtag->display_tag }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($hashtag->posts_count) }}</td>
                                <td class="px-4 py-3">
                                    @if ($hashtag->is_active)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                                            {{ __('hashtag.status_active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                            {{ __('hashtag.status_inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ $hashtag->created_at->translatedFormat('d M Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <form method="POST" action="{{ route('admin.hashtags.toggle-active', $hashtag) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                                                {{ $hashtag->is_active ? __('hashtag.deactivate') : __('hashtag.activate') }}
                                            </button>
                                        </form>
                                        @if ($hashtag->posts_count === 0)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.hashtags.destroy', $hashtag) }}"
                                                data-confirm="{{ __('hashtag.delete_confirm', ['tag' => $hashtag->display_tag]) }}"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                                                    {{ __('common.delete') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
