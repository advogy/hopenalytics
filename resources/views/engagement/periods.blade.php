{{-- Global admins manage engagement periods and their target share (MembershipPeriodController). --}}
@extends('layouts.app')

@section('title', __('engagement.periods_title') . ' — ' . config('app.name'))

@php
    $inputClass = 'w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800';
@endphp

@section('content')
    <x-hub-header hub="goals" />

    <h2 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('engagement.periods_title') }}</h2>
    <p class="mb-6 max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('engagement.periods_subtitle') }}</p>

    <form
        method="POST"
        action="{{ $editing ? route('engagement.periods.update', $editing) : route('engagement.periods.store') }}"
        data-disable-on-submit
        class="mb-8 max-w-3xl rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <h2 class="mb-4 text-sm font-bold text-slate-900 dark:text-white">
            {{ $editing ? __('engagement.period_edit') : __('engagement.period_add') }}
        </h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['name', 'text', __('engagement.period_name'), $editing?->name, ['maxlength' => 100, 'placeholder' => __('engagement.period_name_placeholder')]],
                ['starts_on', 'date', __('engagement.starts_on'), $editing?->starts_on?->toDateString(), []],
                ['ends_on', 'date', __('engagement.ends_on'), $editing?->ends_on?->toDateString(), []],
                ['target_percent', 'number', __('engagement.target_percent'), $editing ? $editing->target_percent + 0 : 10, ['min' => '0.01', 'max' => '100', 'step' => '0.01']],
            ] as [$field, $type, $label, $value, $attributes])
                <div>
                    <label for="{{ $field }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
                    <input
                        type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" required
                        value="{{ old($field, $value) }}"
                        @foreach ($attributes as $attribute => $attributeValue) {{ $attribute }}="{{ $attributeValue }}" @endforeach
                        class="{{ $inputClass }}"
                    >
                    @error($field)
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                {{ __('engagement.period_save') }}
            </button>
            @if ($editing)
                <a href="{{ route('engagement.periods.index') }}" class="text-sm font-medium text-slate-500 hover:underline dark:text-slate-400">{{ __('engagement.period_cancel') }}</a>
            @endif
        </div>
    </form>

    @if ($periods->isEmpty())
        <x-empty-state>{{ __('engagement.periods_empty') }}</x-empty-state>
    @else
        <div class="max-w-3xl overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.period_name') }}</th>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.period') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.col_target') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                    @foreach ($periods as $period)
                        <tr @class(['bg-blue-50/60 dark:bg-blue-950/30' => $editing?->id === $period->id])>
                            <td class="px-4 py-3 font-medium">{{ $period->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">
                                {{ $period->starts_on->translatedFormat('d M Y') }} – {{ $period->ends_on->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $period->target_percent + 0 }}%</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('engagement.periods.index', ['ubah' => $period->id]) }}" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">{{ __('engagement.period_change') }}</a>
                                    <form method="POST" action="{{ route('engagement.periods.destroy', $period) }}" data-confirm="{{ __('engagement.period_delete_confirm', ['name' => $period->name]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">{{ __('engagement.period_delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
