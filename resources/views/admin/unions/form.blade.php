@extends('layouts.app')

@section('title', ($union->exists ? __('accounts.title_edit_uni') : __('accounts.title_add_uni')) . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('admin.accounts.index', ['tab' => 'uni']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back') }}
    </a>

    <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ $union->exists ? __('accounts.title_edit_uni') : __('accounts.title_add_uni') }}
    </h1>

    <form
        method="POST"
        action="{{ $union->exists ? route('admin.unions.update', $union) : route('admin.unions.store') }}"
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @if ($union->exists)
            @method('PUT')
        @endif

        <x-form-field name="name" :label="__('accounts.uni_name')" required :value="$union->name" />

        <x-form-field
            name="coordinator_whatsapp_number"
            :label="__('accounts.uni_coordinator_whatsapp')"
            :hint="__('accounts.uni_coordinator_whatsapp_hint')"
            :value="$union->coordinator_whatsapp_number"
            placeholder="628123456789"
        />

        <x-form-field
            name="whatsapp_group_link"
            :label="__('accounts.uni_whatsapp_group_link')"
            :hint="__('accounts.uni_whatsapp_group_link_hint')"
            :value="$union->whatsapp_group_link"
            placeholder="https://chat.whatsapp.com/…"
        />

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ $union->exists ? __('common.save_changes') : __('accounts.title_add_uni') }}
            </button>
            <a href="{{ route('admin.accounts.index', ['tab' => 'uni']) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>

    @if ($union->exists)
        <form
            method="POST"
            action="{{ route('admin.unions.destroy', $union) }}"
            class="mt-6 max-w-lg"
            data-confirm="{{ __('accounts.deactivate_uni_confirm', ['name' => $union->name]) }}"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                {{ __('accounts.deactivate_uni') }}
            </button>
        </form>
    @endif
@endsection
