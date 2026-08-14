@extends('layouts.app')

@section('title', ($division->exists ? __('accounts.title_edit_divisi') : __('accounts.title_add_divisi')) . ' — ' . config('app.name'))

@section('content')
    <x-entity-crud-form
        :entity="$division"
        :action="$division->exists ? route('admin.divisions.update', $division) : route('admin.divisions.store')"
        :back-url="route('admin.accounts.index', ['tab' => 'divisi'])"
        :title="$division->exists ? __('accounts.title_edit_divisi') : __('accounts.title_add_divisi')"
        :submit-label="$division->exists ? __('common.save_changes') : __('accounts.title_add_divisi')"
        :destroy-action="$division->exists ? route('admin.divisions.destroy', $division) : null"
        :destroy-confirm="__('accounts.deactivate_divisi_confirm', ['name' => $division->name])"
        :destroy-label="__('accounts.deactivate_divisi')"
    >
        <x-form-field name="name" :label="__('accounts.divisi_name')" required :value="$division->name" />
        <x-similar-name-check :route="route('admin.divisions.similar')" :exclude-id="$division->exists ? $division->id : null" />
    </x-entity-crud-form>
@endsection
