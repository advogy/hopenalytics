@extends('layouts.app')

@section('title', ($conference->exists ? __('accounts.title_edit_daerah') : __('accounts.title_add_daerah')) . ' — ' . config('app.name'))

@section('content')
    <x-entity-crud-form
        :entity="$conference"
        :action="$conference->exists ? route('admin.conferences.update', $conference) : route('admin.conferences.store')"
        :back-url="route('admin.accounts.index', ['tab' => 'daerah'])"
        :title="$conference->exists ? __('accounts.title_edit_daerah') : __('accounts.title_add_daerah')"
        :submit-label="$conference->exists ? __('common.save_changes') : __('accounts.title_add_daerah')"
        :destroy-action="$conference->exists ? route('admin.conferences.destroy', $conference) : null"
        :destroy-confirm="__('accounts.deactivate_daerah_confirm', ['name' => $conference->name])"
        :destroy-label="__('accounts.deactivate_daerah')"
    >
        <x-form-field name="name" :label="__('accounts.daerah_name')" required :value="$conference->name" />
        <x-similar-name-check :route="route('admin.conferences.similar')" :exclude-id="$conference->exists ? $conference->id : null" />

        <div class="mb-5">
            @if ($canPickUnion)
                <x-select-field name="union_id" :label="__('common.union')" required wrapper-class="">
                    <option value="">{{ __('accounts.choose_uni_placeholder') }}</option>
                    @foreach ($unions as $union)
                        <option value="{{ $union->id }}" @selected(old('union_id', $conference->union_id) == $union->id)>{{ $union->name }}</option>
                    @endforeach
                </x-select-field>
            @else
                <label class="mb-1.5 block text-sm font-medium">{{ __('common.union') }}</label>
                {{-- admin_uni: always their own union, submitted server-side regardless of this field. --}}
                <input type="hidden" name="union_id" value="{{ $ownUnion->id ?? '' }}">
                <p class="rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                    {{ $ownUnion->name ?? '—' }}
                </p>
            @endif
        </div>

        <x-coordinate-fields :latitude="$conference->latitude" :longitude="$conference->longitude" />
    </x-entity-crud-form>
@endsection
