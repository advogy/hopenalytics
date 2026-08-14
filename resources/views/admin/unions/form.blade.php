@extends('layouts.app')

@section('title', ($union->exists ? __('accounts.title_edit_uni') : __('accounts.title_add_uni')) . ' — ' . config('app.name'))

@section('content')
    <x-entity-crud-form
        :entity="$union"
        :action="$union->exists ? route('admin.unions.update', $union) : route('admin.unions.store')"
        :back-url="route('admin.accounts.index', ['tab' => 'uni'])"
        :title="$union->exists ? __('accounts.title_edit_uni') : __('accounts.title_add_uni')"
        :submit-label="$union->exists ? __('common.save_changes') : __('accounts.title_add_uni')"
        :destroy-action="$union->exists ? route('admin.unions.destroy', $union) : null"
        :destroy-confirm="__('accounts.deactivate_uni_confirm', ['name' => $union->name])"
        :destroy-label="__('accounts.deactivate_uni')"
    >
        <x-form-field name="name" :label="__('accounts.uni_name')" required :value="$union->name" />
        <x-similar-name-check :route="route('admin.unions.similar')" :exclude-id="$union->exists ? $union->id : null" />

        <div class="mb-5">
            @if ($canPickDivision)
                <x-select-field name="division_id" :label="__('common.division')" wrapper-class="">
                    <option value="">{{ __('accounts.choose_divisi_placeholder') }}</option>
                    @foreach ($divisions as $divisionOption)
                        <option value="{{ $divisionOption->id }}" @selected(old('division_id', $union->division_id) == $divisionOption->id)>{{ $divisionOption->name }}</option>
                    @endforeach
                </x-select-field>
            @else
                <label class="mb-1.5 block text-sm font-medium">{{ __('common.division') }}</label>
                {{-- admin_divisi: always their own division, submitted server-side regardless of this field. --}}
                <input type="hidden" name="division_id" value="{{ $ownDivision->id ?? '' }}">
                <p class="rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                    {{ $ownDivision->name ?? '—' }}
                </p>
            @endif
        </div>

        <x-form-field
            name="coordinator_whatsapp_number"
            :label="__('accounts.uni_coordinator_whatsapp')"
            :hint="__('accounts.uni_coordinator_whatsapp_hint')"
            :value="$union->coordinator_whatsapp_number"
            placeholder="628123456789"
        />

        <div class="mb-5">
            <div class="mb-1.5 block text-sm font-medium">{{ __('accounts.uni_groups') }}</div>
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">{{ __('accounts.uni_groups_hint') }}</p>
            <x-group-links-fields name="groups" :groups="$union->exists ? $union->groups : collect()" />
        </div>

        <x-coordinate-fields :latitude="$union->latitude" :longitude="$union->longitude" />
    </x-entity-crud-form>
@endsection
