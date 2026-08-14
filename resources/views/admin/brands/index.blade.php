@extends('layouts.app')

@section('title', __('brands.title') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('brands.title') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('brands.subtitle') }}</p>
        </div>
        <a
            href="{{ route('admin.brands.create') }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
        >
            <x-icon name="plus" class="h-4 w-4" />
            {{ __('brands.add_button') }}
        </a>
    </div>

    <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
        @if ($brands->isEmpty())
            <div class="p-12 text-center">
                <p class="text-slate-500 dark:text-slate-400">{{ __('brands.none_registered') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('brands.col_logo') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('brands.col_name') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('brands.col_domain') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($brands as $brand)
                            <tr>
                                <td class="px-4 py-3">
                                    @if ($brand->logoUrl())
                                        <img src="{{ $brand->logoUrl() }}" alt="{{ $brand->name }}" class="h-8 w-8 shrink-0 rounded object-contain">
                                    @else
                                        <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('brands.default_logo') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-medium">{{ $brand->name }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $brand->domain }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <a href="{{ route('admin.brands.edit', $brand) }}" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                                            {{ __('common.edit') }}
                                        </a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.brands.destroy', $brand) }}"
                                            data-confirm="{{ __('brands.delete_confirm', ['name' => $brand->name]) }}"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                                                {{ __('common.delete') }}
                                            </button>
                                        </form>
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
