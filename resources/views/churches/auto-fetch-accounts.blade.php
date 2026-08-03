@php
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
@endphp

@extends('layouts.app')

@section('title', __('auto_fetch_accounts.title') . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('churches.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back_to_dashboard') }}
    </a>

    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('auto_fetch_accounts.title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('auto_fetch_accounts.subtitle') }}</p>
    </div>

    @if ($socials->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
            <p class="text-slate-500 dark:text-slate-400">{{ __('auto_fetch_accounts.empty') }}</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.name') }}</th>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('auto_fetch_accounts.owner_type') }}</th>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.platform') }}</th>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('auto_fetch_accounts.status') }}</th>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('auto_fetch_accounts.last_fetched') }}</th>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                    @foreach ($socials as $social)
                        <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium">{{ $social->display_name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $social->owner_type_label }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                    {{ $platformLabels[$social->platform->value] }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $social->display_handle }}</td>
                            <td class="px-4 py-3">
                                @if (is_null($social->last_fetched_at))
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ __('auto_fetch_accounts.status_never') }}
                                    </span>
                                @elseif ($social->last_fetch_status === 'failed')
                                    <span
                                        class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/50 dark:text-red-300"
                                        title="{{ $social->last_fetch_error }}"
                                    >
                                        {{ __('auto_fetch_accounts.status_failed') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                        {{ __('auto_fetch_accounts.status_success') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                {{ $social->last_fetched_at?->translatedFormat('d M Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('socials.edit', $social) }}" class="text-xs text-slate-400 hover:text-blue-600 dark:text-slate-500 dark:hover:text-blue-400">
                                    {{ __('common.edit') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
